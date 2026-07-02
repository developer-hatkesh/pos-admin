<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\BalanceType;
use App\Enums\InvoiceStatus;
use App\Enums\SalesReturnStatus;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\Customer;
use App\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CustomerLedgerReportService
{
    public function __construct(private readonly LedgerBalanceService $balances) {}

    public function query(): Builder
    {
        return Customer::query()
            ->with(['ledger.parent'])
            ->where('company_id', app(CurrentCompany::class)->id());
    }

    public function summary(Customer $customer, ?string $fromDate = null, ?string $toDate = null): array
    {
        $opening = $this->openingBalance($customer, $fromDate);
        $totals = $this->periodTotals($customer, $fromDate, $toDate);
        $closing = round($opening + $totals['debit'] - $totals['credit'], 2);

        return [
            'opening' => $opening,
            'debit' => $totals['debit'],
            'credit' => $totals['credit'],
            'closing' => $closing,
            'dr_cr' => $this->balances->balanceType($closing),
            'opening_formatted' => $this->balances->formattedBalance($opening),
            'closing_formatted' => $this->balances->formattedBalance($closing),
        ];
    }

    public function detail(Customer $customer, ?string $fromDate = null, ?string $toDate = null): array
    {
        $summary = $this->summary($customer, $fromDate, $toDate);
        $running = $summary['opening'];
        $rows = $this->transactionRows($customer, $fromDate, $toDate)
            ->sortBy([['date', 'asc'], ['id', 'asc']])
            ->values()
            ->map(function (array $row) use (&$running): array {
                $running = round($running + (float) $row['debit'] - (float) $row['credit'], 2);
                $row['balance'] = $running;
                $row['dr_cr'] = $this->balances->balanceType($running);

                return $row;
            });

        return compact('summary', 'rows');
    }

    private function openingBalance(Customer $customer, ?string $fromDate = null): float
    {
        $opening = $this->signedOpeningBalance($customer);

        if (blank($fromDate)) {
            return round($opening, 2);
        }

        $before = Carbon::parse($fromDate)->subDay()->toDateString();
        $totals = $this->periodTotals($customer, null, $before);

        return round($opening + $totals['debit'] - $totals['credit'], 2);
    }

    private function periodTotals(Customer $customer, ?string $fromDate = null, ?string $toDate = null): array
    {
        $rows = $this->transactionRows($customer, $fromDate, $toDate);

        return [
            'debit' => round((float) $rows->sum('debit'), 2),
            'credit' => round((float) $rows->sum('credit'), 2),
        ];
    }

    private function transactionRows(Customer $customer, ?string $fromDate = null, ?string $toDate = null): Collection
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

        $sales = $customer->salesInvoices()
            ->whereIn('status', [InvoiceStatus::Posted->value, InvoiceStatus::Partial->value, InvoiceStatus::Paid->value])
            ->where(fn (Builder $query): Builder => $dateScope($query, 'invoice_date'))
            ->get()
            ->map(fn ($invoice): array => [
                'id' => 'sales-'.$invoice->id,
                'date' => $invoice->invoice_date,
                'voucher_no' => $invoice->invoice_no,
                'voucher_type' => 'Sales Invoice',
                'particulars' => 'Sales invoice '.$invoice->invoice_no,
                'debit' => (float) $invoice->total,
                'credit' => 0.0,
            ]);

        $receipts = $customer->vouchers()
            ->where('voucher_type', VoucherType::Receipt->value)
            ->where('status', VoucherStatus::Posted->value)
            ->where(fn (Builder $query): Builder => $dateScope($query, 'voucher_date'))
            ->get()
            ->map(fn ($voucher): array => [
                'id' => 'receipt-'.$voucher->id,
                'date' => $voucher->voucher_date,
                'voucher_no' => $voucher->voucher_no,
                'voucher_type' => 'Receipt',
                'particulars' => 'Receipt '.$voucher->voucher_no,
                'debit' => 0.0,
                'credit' => (float) $voucher->amount,
            ]);

        $creditNotePayments = $customer->vouchers()
            ->where('voucher_type', VoucherType::Payment->value)
            ->where('payment_voucher_type', 'credit_note')
            ->where('status', VoucherStatus::Posted->value)
            ->where(fn (Builder $query): Builder => $dateScope($query, 'voucher_date'))
            ->get()
            ->map(fn ($voucher): array => [
                'id' => 'credit-note-payment-'.$voucher->id,
                'date' => $voucher->voucher_date,
                'voucher_no' => $voucher->voucher_no,
                'voucher_type' => 'Credit Note Payment',
                'particulars' => 'Credit note payment '.$voucher->voucher_no,
                'debit' => (float) $voucher->amount,
                'credit' => 0.0,
            ]);

        $returns = $customer->salesReturns()
            ->where('status', SalesReturnStatus::Posted->value)
            ->where(fn (Builder $query): Builder => $dateScope($query, 'return_date'))
            ->get()
            ->map(fn ($return): array => [
                'id' => 'sales-return-'.$return->id,
                'date' => $return->return_date,
                'voucher_no' => $return->return_no,
                'voucher_type' => 'Credit Note',
                'particulars' => 'Credit note '.$return->return_no,
                'debit' => 0.0,
                'credit' => (float) $return->total,
            ]);

        return collect()
            ->concat($sales)
            ->concat($receipts)
            ->concat($creditNotePayments)
            ->concat($returns);
    }

    private function signedOpeningBalance(Customer $customer): float
    {
        $amount = abs((float) $customer->opening_balance);
        $type = $customer->balance_type;

        if ($type instanceof BalanceType) {
            return $type === BalanceType::Credit ? -$amount : $amount;
        }

        return (string) ($type ?? BalanceType::Debit->value) === BalanceType::Credit->value ? -$amount : $amount;
    }
}
