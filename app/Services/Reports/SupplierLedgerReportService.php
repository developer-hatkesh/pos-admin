<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\ExpenseStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PurchaseReturnStatus;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\Supplier;
use App\Models\Voucher;
use App\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SupplierLedgerReportService
{
    public function __construct(private readonly LedgerBalanceService $balances) {}

    public function query(): Builder
    {
        return Supplier::query()
            ->with(['ledger.parent'])
            ->where('company_id', app(CurrentCompany::class)->id());
    }

    public function summary(Supplier $supplier, ?string $fromDate = null, ?string $toDate = null): array
    {
        $opening = $this->openingBalance($supplier, $fromDate);
        $totals = $this->periodTotals($supplier, $fromDate, $toDate);
        $closing = round($opening + $totals['debit'] - $totals['credit'], 2);

        return [
            'opening' => $opening,
            'debit' => $totals['debit'],
            'credit' => $totals['credit'],
            'closing' => $closing,
            'dr_cr' => $this->balanceType($closing),
            'opening_formatted' => $this->formattedBalance($opening),
            'closing_formatted' => $this->formattedBalance($closing),
        ];
    }

    public function detail(Supplier $supplier, ?string $fromDate = null, ?string $toDate = null): array
    {
        $summary = $this->summary($supplier, $fromDate, $toDate);
        $running = $summary['opening'];
        $rows = $this->transactionRows($supplier, $fromDate, $toDate)
            ->sortBy([['date', 'asc'], ['created_at', 'asc'], ['source_id', 'asc'], ['allocation_id', 'asc'], ['id', 'asc']])
            ->values()
            ->map(function (array $row) use (&$running): array {
                $running = round($running + (float) $row['debit'] - (float) $row['credit'], 2);
                $row['balance'] = $running;
                $row['dr_cr'] = $this->balanceType($running);

                return $row;
            });

        return compact('summary', 'rows');
    }

    private function openingBalance(Supplier $supplier, ?string $fromDate = null): float
    {
        $opening = $this->signedOpeningBalance($supplier);

        if (blank($fromDate)) {
            return $opening;
        }

        $before = Carbon::parse($fromDate)->subDay()->toDateString();
        $totals = $this->periodTotals($supplier, null, $before);

        return round($opening + $totals['debit'] - $totals['credit'], 2);
    }

    private function periodTotals(Supplier $supplier, ?string $fromDate = null, ?string $toDate = null): array
    {
        $rows = $this->transactionRows($supplier, $fromDate, $toDate);

        return [
            'debit' => round((float) $rows->sum('debit'), 2),
            'credit' => round((float) $rows->sum('credit'), 2),
        ];
    }

    private function transactionRows(Supplier $supplier, ?string $fromDate = null, ?string $toDate = null): Collection
    {
        $dateScope = function (Builder $query, string $column) use ($fromDate, $toDate): Builder {
            if (filled($fromDate)) {
                $query->whereDate($column, '>=', $fromDate);
            }

            if (filled($toDate)) {
                $query->whereDate($column, '<=', $toDate);
            }

            return $query;
        };

        $purchases = $supplier->purchaseInvoices()
            ->whereIn('status', [InvoiceStatus::Posted->value, InvoiceStatus::Partial->value, InvoiceStatus::Paid->value])
            ->where(fn (Builder $query): Builder => $dateScope($query, 'invoice_date'))
            ->get()
            ->map(fn ($invoice): array => [
                'id' => 'purchase-'.$invoice->id,
                'source_id' => $invoice->id,
                'date' => $invoice->invoice_date,
                'created_at' => $invoice->created_at,
                'allocation_id' => 0,
                'voucher_no' => $invoice->voucherNumber(),
                'voucher_type' => 'Purchase Invoice',
                'particulars' => $this->purchaseParticulars($supplier, $invoice),
                'debit' => 0.0,
                'credit' => (float) $invoice->total,
            ]);

        $expenses = $supplier->expenses()
            ->where('status', ExpenseStatus::Posted->value)
            ->where(fn (Builder $query): Builder => $dateScope($query, 'expense_date'))
            ->get()
            ->map(fn ($expense): array => [
                'id' => 'expense-'.$expense->id,
                'source_id' => $expense->id,
                'date' => $expense->expense_date,
                'created_at' => $expense->created_at,
                'allocation_id' => 0,
                'voucher_no' => $expense->voucher_no,
                'voucher_type' => 'Expense',
                'particulars' => 'Expense '.$expense->voucher_no,
                'debit' => 0.0,
                'credit' => (float) $expense->grand_total_amount,
            ]);

        $payments = $supplier->vouchers()
            ->where('voucher_type', VoucherType::Payment->value)
            ->where('status', VoucherStatus::Posted->value)
            ->where(fn (Builder $query): Builder => $dateScope($query, 'voucher_date'))
            ->with(['allocations' => fn ($query) => $query->with(['purchaseInvoice', 'expense'])->orderBy('id')])
            ->get()
            ->flatMap(fn (Voucher $voucher): array => $this->paymentRows($voucher));

        $purchaseReturnReceipts = $supplier->vouchers()
            ->where('voucher_type', VoucherType::Receipt->value)
            ->where('receipt_voucher_type', 'purchase_return')
            ->where('status', VoucherStatus::Posted->value)
            ->where(fn (Builder $query): Builder => $dateScope($query, 'voucher_date'))
            ->get()
            ->map(fn ($voucher): array => [
                'id' => 'purchase-return-receipt-'.$voucher->id,
                'source_id' => $voucher->id,
                'date' => $voucher->voucher_date,
                'created_at' => $voucher->created_at,
                'allocation_id' => 0,
                'voucher_no' => $voucher->voucher_no,
                'voucher_type' => 'Credit Note Receipt',
                'particulars' => 'Credit note receipt '.$voucher->voucher_no,
                'debit' => 0.0,
                'credit' => (float) $voucher->amount,
            ]);

        $returns = $supplier->purchaseReturns()
            ->where('status', PurchaseReturnStatus::Posted->value)
            ->where(fn (Builder $query): Builder => $dateScope($query, 'return_date'))
            ->get()
            ->map(fn ($return): array => [
                'id' => 'purchase-return-'.$return->id,
                'source_id' => $return->id,
                'date' => $return->return_date,
                'created_at' => $return->created_at,
                'allocation_id' => 0,
                'voucher_no' => $return->return_no,
                'voucher_type' => 'Credit Note',
                'particulars' => 'Credit note '.$return->return_no,
                'debit' => (float) $return->total,
                'credit' => 0.0,
            ]);

        return collect()
            ->concat($purchases)
            ->concat($expenses)
            ->concat($payments)
            ->concat($purchaseReturnReceipts)
            ->concat($returns);
    }

    private function paymentRows(Voucher $voucher): array
    {
        if ($voucher->allocations->isEmpty()) {
            return [$this->paymentRow($voucher, 'payment-'.$voucher->id, 'Payment '.$voucher->voucher_no, (string) $voucher->amount)];
        }

        $rows = [];
        $allocatedMinor = 0;

        foreach ($voucher->allocations as $allocation) {
            $documentNumber = null;
            $documentType = null;

            if ($allocation->purchase_invoice_id !== null) {
                $documentNumber = $allocation->purchaseInvoice?->displayReference() ?: 'deleted purchase invoice #'.$allocation->purchase_invoice_id;
                $documentType = 'purchase invoice';
            } elseif ($allocation->expense_id !== null) {
                $documentNumber = $allocation->expense?->voucher_no ?: 'deleted expense #'.$allocation->expense_id;
                $documentType = 'expense';
            }

            if ($documentNumber === null) {
                continue;
            }

            $amountMinor = $this->minorUnits((string) $allocation->amount);
            $allocatedMinor += $amountMinor;
            $rows[] = $this->paymentRow(
                voucher: $voucher,
                id: 'payment-'.$voucher->id.'-allocation-'.$allocation->id,
                particulars: 'Payment '.$voucher->voucher_no.' against '.$documentType.' '.$documentNumber,
                amount: $this->decimalFromMinorUnits($amountMinor),
                allocationId: $allocation->id,
                againstDocument: $documentNumber,
            );
        }

        $unallocatedMinor = $this->minorUnits((string) $voucher->amount) - $allocatedMinor;

        if ($unallocatedMinor > 0) {
            $rows[] = $this->paymentRow(
                voucher: $voucher,
                id: 'payment-'.$voucher->id.'-unallocated',
                particulars: 'Payment '.$voucher->voucher_no.' – Unallocated supplier payment',
                amount: $this->decimalFromMinorUnits($unallocatedMinor),
                allocationId: PHP_INT_MAX,
            );
        }

        return $rows;
    }

    private function paymentRow(Voucher $voucher, string $id, string $particulars, string $amount, int $allocationId = 0, ?string $againstDocument = null): array
    {
        return [
            'id' => $id,
            'source_id' => $voucher->id,
            'allocation_id' => $allocationId,
            'date' => $voucher->voucher_date,
            'created_at' => $voucher->created_at,
            'voucher_no' => $voucher->voucher_no,
            'voucher_type' => 'Payment',
            'against_document' => $againstDocument,
            'particulars' => $particulars,
            'debit' => (float) $amount,
            'credit' => 0.0,
        ];
    }

    private function purchaseParticulars(Supplier $supplier, $invoice): string
    {
        $reference = $invoice->supplierInvoiceNumber() ?: $invoice->voucherNumber();

        return $supplier->name.' ('.$reference.')';
    }

    private function minorUnits(string $amount): int
    {
        $normalized = ltrim(trim($amount), '+');
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '-');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $minor = ((int) ($whole ?: '0') * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        return $negative ? -$minor : $minor;
    }

    private function decimalFromMinorUnits(int $amount): string
    {
        $negative = $amount < 0 ? '-' : '';
        $amount = abs($amount);

        return $negative.intdiv($amount, 100).'.'.str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
    }

    private function signedOpeningBalance(Supplier $supplier): float
    {
        $amount = abs((float) $supplier->opening_balance);

        return (string) ($supplier->balance_type?->value ?? $supplier->balance_type ?? 'Cr') === 'Dr'
            ? $amount
            : -$amount;
    }

    private function formattedBalance(float $signedBalance): string
    {
        if (round($signedBalance, 2) === 0.0) {
            return CurrencyService::format(0);
        }

        return CurrencyService::format(abs($signedBalance)).' '.$this->balanceType($signedBalance);
    }

    private function balanceType(float $signedBalance): string
    {
        if (round($signedBalance, 2) === 0.0) {
            return '';
        }

        return $signedBalance > 0 ? 'Dr' : 'Cr';
    }

    private function emptySummary(): array
    {
        return [
            'opening' => 0.0, 'debit' => 0.0, 'credit' => 0.0, 'closing' => 0.0, 'dr_cr' => '',
            'opening_formatted' => CurrencyService::format(0), 'closing_formatted' => CurrencyService::format(0),
        ];
    }
}
