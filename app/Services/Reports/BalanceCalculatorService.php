<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\LedgerType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BalanceCalculatorService
{
    public function ledgerBalances(int $companyId, string $asOnDate): Collection
    {
        $movement = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_id')
            ->where('journal_entries.company_id', $companyId)
            ->whereDate('journal_entries.entry_date', '<=', $asOnDate)
            ->groupBy('journal_lines.ledger_id')
            ->selectRaw('journal_lines.ledger_id, COALESCE(SUM(journal_lines.debit), 0) as debit_total, COALESCE(SUM(journal_lines.credit), 0) as credit_total');

        return DB::table('ledgers')
            ->leftJoinSub($movement, 'movement', fn ($join) => $join->on('movement.ledger_id', '=', 'ledgers.id'))
            ->leftJoin('chart_of_accounts', 'chart_of_accounts.account_code', '=', 'ledgers.nominal_code')
            ->leftJoin('account_categories', 'account_categories.account_category_id', '=', 'chart_of_accounts.account_category_id')
            ->leftJoin('account_classes', 'account_classes.account_class_id', '=', 'account_categories.account_class_id')
            ->where('ledgers.company_id', $companyId)
            ->select([
                'ledgers.id',
                'ledgers.name',
                'ledgers.nominal_code',
                'ledgers.type',
                'ledgers.opening_balance',
                'ledgers.balance_type',
                'chart_of_accounts.account_id',
                'chart_of_accounts.account_name',
                'chart_of_accounts.normal_balance_type',
                'account_categories.account_category_id',
                'account_categories.account_category_code',
                'account_categories.account_category_name',
                'account_classes.account_class_code',
                'account_classes.account_class_name',
            ])
            ->selectRaw('COALESCE(movement.debit_total, 0) as debit_total')
            ->selectRaw('COALESCE(movement.credit_total, 0) as credit_total')
            ->orderBy('ledgers.nominal_code')
            ->get()
            ->map(function (object $row): array {
                $signedOpening = $this->signedOpeningBalance($row);
                $signedBalance = round($signedOpening + (float) $row->debit_total - (float) $row->credit_total, 2);

                return [
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                    'code' => (string) $row->nominal_code,
                    'type' => (string) $row->type,
                    'class_code' => $row->account_class_code ?: $this->classCodeFromLedgerType((string) $row->type),
                    'class_name' => $row->account_class_name ?: $this->classNameFromLedgerType((string) $row->type),
                    'category_id' => $row->account_category_id ? (int) $row->account_category_id : null,
                    'category_code' => $row->account_category_code ?: $this->classCodeFromLedgerType((string) $row->type),
                    'category_name' => $row->account_category_name ?: $this->classNameFromLedgerType((string) $row->type),
                    'debit_total' => round((float) $row->debit_total, 2),
                    'credit_total' => round((float) $row->credit_total, 2),
                    'signed_balance' => $signedBalance,
                    'statement_amount' => $this->statementAmount((string) $row->type, $signedBalance),
                ];
            });
    }

    private function signedOpeningBalance(object $row): float
    {
        $amount = abs((float) $row->opening_balance);
        $balanceType = (string) $row->balance_type;

        if ($balanceType === 'Cr') {
            return -$amount;
        }

        if ($balanceType === 'Dr') {
            return $amount;
        }

        $normalBalance = strtoupper((string) $row->normal_balance_type);

        if ($normalBalance === 'CREDIT') {
            return -$amount;
        }

        if ($normalBalance === 'DEBIT') {
            return $amount;
        }

        return in_array((string) $row->type, [LedgerType::Liability->value, LedgerType::Income->value, LedgerType::Equity->value], true)
            ? -$amount
            : $amount;
    }

    private function statementAmount(string $ledgerType, float $signedBalance): float
    {
        return round(match ($ledgerType) {
            LedgerType::Liability->value, LedgerType::Equity->value, LedgerType::Income->value => -$signedBalance,
            default => $signedBalance,
        }, 2);
    }

    private function classCodeFromLedgerType(string $ledgerType): string
    {
        return match ($ledgerType) {
            LedgerType::Asset->value => 'ASSET',
            LedgerType::Liability->value => 'LIABILITY',
            LedgerType::Equity->value => 'EQUITY',
            LedgerType::Income->value => 'INCOME',
            LedgerType::Expense->value => 'EXPENSE',
            default => strtoupper($ledgerType),
        };
    }

    private function classNameFromLedgerType(string $ledgerType): string
    {
        return match ($ledgerType) {
            LedgerType::Asset->value => 'Assets',
            LedgerType::Liability->value => 'Liabilities',
            LedgerType::Equity->value => 'Equity',
            LedgerType::Income->value => 'Income',
            LedgerType::Expense->value => 'Expenses',
            default => ucfirst($ledgerType),
        };
    }
}
