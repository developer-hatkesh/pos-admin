<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

class ChartOfAccountMasterSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['account_id' => 1, 'account_category_id' => 1, 'account_code' => '1000', 'account_name' => 'Cash', 'normal_balance_type' => 'DEBIT', 'is_active' => true],
            ['account_id' => 2, 'account_category_id' => 1, 'account_code' => '1100', 'account_name' => 'Bank Account', 'normal_balance_type' => 'DEBIT', 'is_active' => true],
            ['account_id' => 3, 'account_category_id' => 1, 'account_code' => '1200', 'account_name' => 'Accounts Receivable', 'normal_balance_type' => 'DEBIT', 'is_active' => true],
            ['account_id' => 4, 'account_category_id' => 3, 'account_code' => '2000', 'account_name' => 'Accounts Payable', 'normal_balance_type' => 'CREDIT', 'is_active' => true],
        ];

        foreach ($accounts as $account) {
            ChartOfAccount::query()->updateOrCreate(
                ['account_id' => $account['account_id']],
                [
                    'account_category_id' => $account['account_category_id'],
                    'account_code' => $account['account_code'],
                    'account_name' => $account['account_name'],
                    'normal_balance_type' => $account['normal_balance_type'],
                    'is_active' => $account['is_active'],
                ],
            );
        }
    }
}
