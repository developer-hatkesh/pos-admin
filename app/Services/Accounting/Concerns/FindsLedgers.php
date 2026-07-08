<?php

declare(strict_types=1);

namespace App\Services\Accounting\Concerns;

use App\Enums\LedgerType;
use App\Enums\Status;
use App\Models\ChartOfAccount;
use App\Models\Ledger;
use RuntimeException;

trait FindsLedgers
{
    protected function receivableLedger(int $companyId): Ledger
    {
        $ledger = Ledger::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->whereIn('nominal_code', ['1200', '1100'])
            ->get()
            ->first(fn (Ledger $ledger): bool => $this->isReceivableLedger($ledger));

        return $ledger ?: $this->ledgerByCode($companyId, '1200');
    }

    protected function ledgerByCode(int $companyId, string $nominalCode): Ledger
    {
        $ledger = Ledger::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('nominal_code', $nominalCode)
            ->first();

        if ($ledger) {
            return $ledger;
        }

        $chartAccount = ChartOfAccount::query()
            ->with('accountCategory.accountClass')
            ->where('account_code', $nominalCode)
            ->where('is_active', true)
            ->first();

        if ($chartAccount) {
            return Ledger::query()
                ->withoutGlobalScope('company')
                ->create([
                    'company_id' => $companyId,
                    'nominal_code' => $chartAccount->account_code,
                    'name' => $chartAccount->account_name,
                    'type' => $this->ledgerTypeForChartAccount($chartAccount),
                    'is_control_account' => true,
                    'opening_balance' => 0,
                    'balance_type' => $this->balanceTypeForChartAccount($chartAccount),
                    'status' => Status::Active,
                ]);
        }

        $default = $this->legacyLedgerDefault($nominalCode);

        if (! $default) {
            throw new RuntimeException("Ledger {$nominalCode} is missing for company {$companyId}.");
        }

        return Ledger::query()
            ->withoutGlobalScope('company')
            ->create([
                'company_id' => $companyId,
                'nominal_code' => $nominalCode,
                'name' => $default['name'],
                'type' => $default['type'],
                'is_control_account' => true,
                'opening_balance' => 0,
                'balance_type' => $default['balance_type'],
                'status' => Status::Active,
            ]);
    }

    private function ledgerTypeForChartAccount(ChartOfAccount $chartAccount): string
    {
        return match ($chartAccount->accountCategory?->accountClass?->account_class_code) {
            'LIABILITY' => LedgerType::Liability->value,
            'EQUITY' => LedgerType::Equity->value,
            'INCOME' => LedgerType::Income->value,
            'EXPENSE' => LedgerType::Expense->value,
            default => LedgerType::Asset->value,
        };
    }

    private function balanceTypeForChartAccount(ChartOfAccount $chartAccount): string
    {
        return strtoupper((string) $chartAccount->normal_balance_type) === 'CREDIT' ? 'Cr' : 'Dr';
    }

    private function isReceivableLedger(Ledger $ledger): bool
    {
        $text = strtolower($ledger->nominal_code.' '.$ledger->name);

        return str_contains($text, 'receivable')
            || str_contains($text, 'debtor')
            || str_contains($text, 'trade debt');
    }

    private function legacyLedgerDefault(string $nominalCode): ?array
    {
        return match ($nominalCode) {
            '1100' => [
                'name' => 'Trade Debtors',
                'type' => LedgerType::Asset->value,
                'balance_type' => 'Dr',
            ],
            '1200' => [
                'name' => 'Bank',
                'type' => LedgerType::Asset->value,
                'balance_type' => 'Dr',
            ],
            '2201' => [
                'name' => 'VAT Output',
                'type' => LedgerType::Liability->value,
                'balance_type' => 'Cr',
            ],
            '2202' => [
                'name' => 'VAT Input',
                'type' => LedgerType::Asset->value,
                'balance_type' => 'Dr',
            ],
            '4000' => [
                'name' => 'Sales',
                'type' => LedgerType::Income->value,
                'balance_type' => 'Cr',
            ],
            default => null,
        };
    }
}
