<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\BankTransactionType;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;

class BankLedgerReportService
{
    public function __construct(private readonly LedgerBalanceService $balances) {}

    public function query(): Builder
    {
        return BankAccount::query()
            ->with(['ledger.parent'])
            ->where('company_id', app(CurrentCompany::class)->id());
    }

    public function summary(BankAccount $bankAccount, ?string $fromDate = null, ?string $toDate = null): array
    {
        $opening = $this->openingBalance($bankAccount, $fromDate);
        $totals = $this->periodTotals($bankAccount, $fromDate, $toDate);
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

    public function detail(BankAccount $bankAccount, ?string $fromDate = null, ?string $toDate = null): array
    {
        $summary = $this->summary($bankAccount, $fromDate, $toDate);
        $running = $summary['opening'];

        $rows = $this->transactionsQuery($bankAccount, $fromDate, $toDate)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get()
            ->map(function (BankTransaction $transaction) use (&$running): array {
                $debit = $transaction->type === BankTransactionType::Deposit ? (float) $transaction->amount : 0.0;
                $credit = $transaction->type === BankTransactionType::Withdrawal ? (float) $transaction->amount : 0.0;
                $running = round($running + $debit - $credit, 2);

                return [
                    'date' => $transaction->transaction_date,
                    'voucher_no' => $transaction->reference ?: 'BANK-'.$transaction->id,
                    'voucher_type' => $this->voucherType($transaction),
                    'particulars' => $this->particulars($transaction),
                    'debit' => $debit,
                    'credit' => $credit,
                    'balance' => $running,
                    'dr_cr' => $this->balances->balanceType($running),
                ];
            });

        return compact('summary', 'rows');
    }

    public function lastTransactionDate(BankAccount $bankAccount, ?string $fromDate = null, ?string $toDate = null): ?string
    {
        return $this->transactionsQuery($bankAccount, $fromDate, $toDate)->max('transaction_date');
    }

    public function closingBalanceSql(?string $fromDate = null, ?string $toDate = null): array
    {
        $beforeSql = '';
        $periodSql = '';
        $bindings = [];

        if (filled($fromDate)) {
            $beforeSql = ' AND bank_transactions.transaction_date < ?';
            $bindings[] = $fromDate;
        }

        if (filled($fromDate)) {
            $periodSql .= ' AND bank_transactions.transaction_date >= ?';
            $bindings[] = $fromDate;
        }

        if (filled($toDate)) {
            $periodSql .= ' AND bank_transactions.transaction_date <= ?';
            $bindings[] = $toDate;
        }

        $openingSql = "COALESCE(bank_accounts.opening_balance, 0) + COALESCE((
            SELECT SUM(CASE WHEN bank_transactions.type = 'deposit' THEN bank_transactions.amount ELSE -bank_transactions.amount END)
            FROM bank_transactions
            WHERE bank_transactions.bank_account_id = bank_accounts.id{$beforeSql}
        ), 0)";

        $periodSql = "COALESCE((
            SELECT SUM(CASE WHEN bank_transactions.type = 'deposit' THEN bank_transactions.amount ELSE -bank_transactions.amount END)
            FROM bank_transactions
            WHERE bank_transactions.bank_account_id = bank_accounts.id{$periodSql}
        ), 0)";

        return ['('.$openingSql.' + '.$periodSql.')', $bindings];
    }

    private function openingBalance(BankAccount $bankAccount, ?string $fromDate = null): float
    {
        $opening = (float) $bankAccount->opening_balance;

        if (blank($fromDate)) {
            return round($opening, 2);
        }

        $before = $this->transactionsQuery($bankAccount, null, null)
            ->whereDate('transaction_date', '<', $fromDate)
            ->get()
            ->sum(fn (BankTransaction $transaction): float => $transaction->type === BankTransactionType::Deposit
                ? (float) $transaction->amount
                : -(float) $transaction->amount);

        return round($opening + $before, 2);
    }

    private function periodTotals(BankAccount $bankAccount, ?string $fromDate = null, ?string $toDate = null): array
    {
        $rows = $this->transactionsQuery($bankAccount, $fromDate, $toDate)
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount ELSE 0 END), 0) as debit_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END), 0) as credit_total")
            ->first();

        return [
            'debit' => round((float) ($rows?->debit_total ?? 0), 2),
            'credit' => round((float) ($rows?->credit_total ?? 0), 2),
        ];
    }

    private function transactionsQuery(BankAccount $bankAccount, ?string $fromDate = null, ?string $toDate = null): Builder
    {
        return BankTransaction::query()
            ->with(['customer', 'supplier', 'party', 'ledger', 'journalEntry'])
            ->where('company_id', $bankAccount->company_id)
            ->where('bank_account_id', $bankAccount->id)
            ->when(filled($fromDate), fn (Builder $query): Builder => $query->whereDate('transaction_date', '>=', $fromDate))
            ->when(filled($toDate), fn (Builder $query): Builder => $query->whereDate('transaction_date', '<=', $toDate));
    }

    private function voucherType(BankTransaction $transaction): string
    {
        if ($transaction->journalEntry?->source_type?->value === 'manual') {
            return 'Journal';
        }

        if ($transaction->customer_id !== null && $transaction->type === BankTransactionType::Deposit) {
            return 'Sales Receipt';
        }

        if ($transaction->supplier_id !== null && $transaction->type === BankTransactionType::Withdrawal) {
            return 'Purchase Payment';
        }

        return match ($transaction->type) {
            BankTransactionType::Deposit => 'Deposit',
            BankTransactionType::Withdrawal => 'Withdrawal',
        };
    }

    private function particulars(BankTransaction $transaction): string
    {
        $counterparty = $transaction->customer?->name
            ?: $transaction->supplier?->name
            ?: $transaction->party?->name
            ?: $transaction->ledger?->name;

        return collect([$transaction->reference, $counterparty])->filter()->implode(' - ')
            ?: ($transaction->type === BankTransactionType::Deposit ? 'Bank deposit' : 'Bank withdrawal');
    }
}
