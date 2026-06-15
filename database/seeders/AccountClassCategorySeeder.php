<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AccountCategory;
use App\Models\AccountClass;
use Illuminate\Database\Seeder;

class AccountClassCategorySeeder extends Seeder
{
    public function run(): void
    {
        $classes = [
            ['account_class_id' => 1, 'account_class_code' => 'ASSET', 'account_class_name' => 'Assets'],
            ['account_class_id' => 2, 'account_class_code' => 'LIABILITY', 'account_class_name' => 'Liabilities'],
            ['account_class_id' => 3, 'account_class_code' => 'EQUITY', 'account_class_name' => 'Equity'],
            ['account_class_id' => 4, 'account_class_code' => 'INCOME', 'account_class_name' => 'Income'],
            ['account_class_id' => 5, 'account_class_code' => 'EXPENSE', 'account_class_name' => 'Expenses'],
        ];

        foreach ($classes as $class) {
            AccountClass::query()->updateOrCreate(
                ['account_class_id' => $class['account_class_id']],
                [
                    'account_class_code' => $class['account_class_code'],
                    'account_class_name' => $class['account_class_name'],
                ],
            );
        }

        $categories = [
            ['account_category_id' => 1, 'account_class_id' => 1, 'account_category_code' => 'CUR ASSET', 'account_category_name' => 'Current Assets'],
            ['account_category_id' => 2, 'account_class_id' => 1, 'account_category_code' => 'FIX ASSET', 'account_category_name' => 'Fixed Assets'],
            ['account_category_id' => 3, 'account_class_id' => 2, 'account_category_code' => 'CUR LIAB', 'account_category_name' => 'Current Liabilities'],
            ['account_category_id' => 4, 'account_class_id' => 4, 'account_category_code' => 'SALES', 'account_category_name' => 'Sales Revenue'],
            ['account_category_id' => 5, 'account_class_id' => 5, 'account_category_code' => 'OPER EXP', 'account_category_name' => 'Operating Expenses'],
        ];

        foreach ($categories as $category) {
            AccountCategory::query()->updateOrCreate(
                ['account_category_id' => $category['account_category_id']],
                [
                    'account_class_id' => $category['account_class_id'],
                    'account_category_code' => $category['account_category_code'],
                    'account_category_name' => $category['account_category_name'],
                ],
            );
        }
    }
}
