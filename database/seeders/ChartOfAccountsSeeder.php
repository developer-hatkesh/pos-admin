<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\LedgerType;
use App\Enums\Status;
use App\Models\Company;
use App\Models\Ledger;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->firstOrFail();

        $ledgers = [
            ['1000', 'Stock', LedgerType::Asset, false],
            ['1100', 'Trade Debtors', LedgerType::Asset, true],
            ['1200', 'Bank', LedgerType::Asset, true],
            ['2100', 'Trade Creditors', LedgerType::Liability, true],
            ['2200', 'VAT Control', LedgerType::Liability, true],
            ['2201', 'VAT Output', LedgerType::Liability, true],
            ['2202', 'VAT Input', LedgerType::Asset, true],
            ['3000', 'Retained Earnings', LedgerType::Equity, true],
            ['4000', 'Sales', LedgerType::Income, false],
            ['5000', 'Purchases', LedgerType::Expense, false],
        ];

        foreach ($ledgers as [$code, $name, $type, $control]) {
            Ledger::query()->withoutGlobalScope('company')->updateOrCreate(
                ['company_id' => $company->id, 'nominal_code' => $code],
                [
                    'name' => $name,
                    'type' => $type,
                    'is_control_account' => $control,
                    'status' => Status::Active,
                ],
            );
        }
    }
}
