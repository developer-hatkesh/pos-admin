<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\ExpenseStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PurchaseReturnStatus;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\Supplier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use App\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;

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
            ->sortBy([['date', 'asc'], ['id', 'asc']])
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
                'date' => $invoice->invoice_date,
                'voucher_no' => $invoice->invoice_no,
                'voucher_type' => 'Purchase Invoice',
                'particulars' => 'Purchase invoice '.$invoice->invoice_no,
                'debit' => 0.0,
                'credit' => (float) $invoice->total,
            ]);

        $expenses = $supplier->expenses()
            ->where('status', ExpenseStatus::Posted->value)
            ->where(fn (Builder $query): Builder => $dateScope($query, 'expense_date'))
            ->get()
            ->map(fn ($expense): array => [
                'id' => 'expense-'.$expense->id,
                'date' => $expense->expense_date,
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
            ->get()
            ->map(fn ($voucher): array => [
                'id' => 'payment-'.$voucher->id,
                'date' => $voucher->voucher_date,
                'voucher_no' => $voucher->voucher_no,
                'voucher_type' => 'Payment',
                'particulars' => 'Payment '.$voucher->voucher_no,
                'debit' => (float) $voucher->amount,
                'credit' => 0.0,
            ]);

        $returns = $supplier->purchaseReturns()
            ->where('status', PurchaseReturnStatus::Posted->value)
            ->where(fn (Builder $query): Builder => $dateScope($query, 'return_date'))
            ->get()
            ->map(fn ($return): array => [
                'id' => 'purchase-return-'.$return->id,
                'date' => $return->return_date,
                'voucher_no' => $return->return_no,
                'voucher_type' => 'Purchase Return',
                'particulars' => 'Purchase return '.$return->return_no,
                'debit' => (float) $return->total,
                'credit' => 0.0,
            ]);

        return collect()
            ->concat($purchases)
            ->concat($expenses)
            ->concat($payments)
            ->concat($returns);
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
