<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AccountCategory;
use App\Models\AccountClass;
use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

class ChartOfAccountMasterSeeder extends Seeder
{
    public function run(): void
    {
        ChartOfAccount::query()->delete();
        AccountCategory::query()->delete();

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
            ['account_category_id' => 3, 'account_class_id' => 1, 'account_category_code' => 'NONCUR ASSET', 'account_category_name' => 'Non-current Assets'],
            ['account_category_id' => 4, 'account_class_id' => 2, 'account_category_code' => 'CUR LIAB', 'account_category_name' => 'Current Liabilities'],
            ['account_category_id' => 5, 'account_class_id' => 2, 'account_category_code' => 'NONCUR LIAB', 'account_category_name' => 'Non-current Liabilities'],
            ['account_category_id' => 6, 'account_class_id' => 3, 'account_category_code' => 'EQUITY', 'account_category_name' => 'Capital and Reserves'],
            ['account_category_id' => 7, 'account_class_id' => 4, 'account_category_code' => 'SALES', 'account_category_name' => 'Sales Revenue'],
            ['account_category_id' => 8, 'account_class_id' => 4, 'account_category_code' => 'OTHER INC', 'account_category_name' => 'Other Income'],
            ['account_category_id' => 9, 'account_class_id' => 5, 'account_category_code' => 'COGS', 'account_category_name' => 'Cost of Sales'],
            ['account_category_id' => 10, 'account_class_id' => 5, 'account_category_code' => 'OPER EXP', 'account_category_name' => 'Operating Expenses'],
            ['account_category_id' => 11, 'account_class_id' => 5, 'account_category_code' => 'STAFF EXP', 'account_category_name' => 'Staff Costs'],
            ['account_category_id' => 12, 'account_class_id' => 5, 'account_category_code' => 'ADMIN EXP', 'account_category_name' => 'Administrative Expenses'],
            ['account_category_id' => 13, 'account_class_id' => 5, 'account_category_code' => 'FIN EXP', 'account_category_name' => 'Finance Costs'],
            ['account_category_id' => 14, 'account_class_id' => 5, 'account_category_code' => 'TAX EXP', 'account_category_name' => 'Tax Expense'],
        ];

        foreach ($categories as $category) {
            AccountCategory::query()->create($category);
        }

        $accounts = [
            [1, '1000', 'Cash', 'DEBIT'],
            [1, '1010', 'Petty Cash', 'DEBIT'],
            [1, '1100', 'Bank Current Account', 'DEBIT'],
            [1, '1110', 'Bank Savings Account', 'DEBIT'],
            [1, '1200', 'Accounts Receivable / Trade Debtors', 'DEBIT'],
            [1, '1210', 'Allowance for Doubtful Debts', 'CREDIT'],
            [1, '1300', 'Inventory / Stock', 'DEBIT'],
            [1, '1400', 'VAT Input', 'DEBIT'],
            [1, '1410', 'Prepayments', 'DEBIT'],
            [1, '1420', 'Accrued Income', 'DEBIT'],
            [2, '1500', 'Plant and Machinery', 'DEBIT'],
            [2, '1510', 'Accumulated Depreciation - Plant and Machinery', 'CREDIT'],
            [2, '1520', 'Fixtures and Fittings', 'DEBIT'],
            [2, '1530', 'Accumulated Depreciation - Fixtures and Fittings', 'CREDIT'],
            [2, '1540', 'Motor Vehicles', 'DEBIT'],
            [2, '1550', 'Accumulated Depreciation - Motor Vehicles', 'CREDIT'],
            [2, '1560', 'Computer Equipment', 'DEBIT'],
            [2, '1570', 'Accumulated Depreciation - Computer Equipment', 'CREDIT'],
            [4, '2000', 'Accounts Payable / Trade Creditors', 'CREDIT'],
            [4, '2100', 'Supplier Control Account', 'CREDIT'],
            [4, '2200', 'VAT Control', 'CREDIT'],
            [4, '2201', 'VAT Output', 'CREDIT'],
            [4, '2210', 'PAYE / NIC Payable', 'CREDIT'],
            [4, '2220', 'Corporation Tax Payable', 'CREDIT'],
            [4, '2230', 'Accruals', 'CREDIT'],
            [4, '2240', 'Deferred Income', 'CREDIT'],
            [4, '2300', 'Credit Card Payable', 'CREDIT'],
            [5, '2500', 'Bank Loan', 'CREDIT'],
            [5, '2510', 'Hire Purchase Liability', 'CREDIT'],
            [5, '2520', 'Director Loan Account', 'CREDIT'],
            [6, '3000', 'Share Capital / Owner Capital', 'CREDIT'],
            [6, '3100', 'Drawings', 'DEBIT'],
            [6, '3200', 'Retained Earnings', 'CREDIT'],
            [6, '3300', 'Current Year Profit / Loss', 'CREDIT'],
            [7, '4000', 'Sales', 'CREDIT'],
            [7, '4010', 'Sales - Retail', 'CREDIT'],
            [7, '4020', 'Sales - Wholesale', 'CREDIT'],
            [7, '4030', 'Sales Returns', 'DEBIT'],
            [7, '4040', 'Sales Discounts', 'DEBIT'],
            [8, '4200', 'Other Income', 'CREDIT'],
            [8, '4210', 'Bank Interest Received', 'CREDIT'],
            [8, '4220', 'Commission Income', 'CREDIT'],
            [9, '5000', 'Purchases', 'DEBIT'],
            [9, '5010', 'Purchase Returns', 'CREDIT'],
            [9, '5020', 'Purchase Discounts', 'CREDIT'],
            [9, '5100', 'Carriage Inwards', 'DEBIT'],
            [9, '5200', 'Direct Costs', 'DEBIT'],
            [10, '6000', 'Rent', 'DEBIT'],
            [10, '6010', 'Rates', 'DEBIT'],
            [10, '6020', 'Utilities', 'DEBIT'],
            [10, '6030', 'Telephone and Internet', 'DEBIT'],
            [10, '6040', 'Insurance', 'DEBIT'],
            [10, '6050', 'Repairs and Maintenance', 'DEBIT'],
            [11, '7000', 'Wages and Salaries', 'DEBIT'],
            [11, '7010', 'Employer National Insurance', 'DEBIT'],
            [11, '7020', 'Pension Contributions', 'DEBIT'],
            [11, '7030', 'Staff Training', 'DEBIT'],
            [12, '7100', 'Office Expenses', 'DEBIT'],
            [12, '7110', 'Printing and Stationery', 'DEBIT'],
            [12, '7120', 'Postage', 'DEBIT'],
            [12, '7130', 'Software Subscriptions', 'DEBIT'],
            [12, '7140', 'Professional Fees', 'DEBIT'],
            [12, '7150', 'Accountancy Fees', 'DEBIT'],
            [12, '7160', 'Legal Fees', 'DEBIT'],
            [12, '7170', 'Advertising and Marketing', 'DEBIT'],
            [12, '7180', 'Travel and Subsistence', 'DEBIT'],
            [12, '7190', 'Bank Charges', 'DEBIT'],
            [13, '8000', 'Loan Interest', 'DEBIT'],
            [13, '8010', 'Bank Interest Paid', 'DEBIT'],
            [13, '8020', 'Finance Charges', 'DEBIT'],
            [14, '9000', 'Corporation Tax Expense', 'DEBIT'],
            [14, '9010', 'Depreciation Expense', 'DEBIT'],
            [14, '9020', 'Bad Debt Expense', 'DEBIT'],
        ];

        foreach ($accounts as $index => [$categoryId, $code, $name, $normalBalance]) {
            ChartOfAccount::query()->create([
                'account_id' => $index + 1,
                'account_category_id' => $categoryId,
                'account_code' => $code,
                'account_name' => $name,
                'normal_balance_type' => $normalBalance,
                'opening_balance' => 0,
                'is_active' => true,
                'created_at' => now(),
            ]);
        }
    }
}
