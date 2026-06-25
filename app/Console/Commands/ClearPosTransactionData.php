<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClearPosTransactionData extends Command
{
    protected $signature = 'transactions:clear-pos-data
        {--force : Run without confirmation}
        {--stock=100 : Opening stock quantity to set for each non-service product item}
        {--keep-expenses : Keep expense entries}';

    protected $description = 'Clear POS sales, purchase, voucher, invoice, journal, bank, VAT, and stock transaction data, then reset product stock.';

    public function handle(): int
    {
        $stock = (float) $this->option('stock');

        if ($stock < 0) {
            $this->error('Stock quantity must be zero or greater.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm(
            'This will permanently delete sales, returns, purchases, invoices, vouchers, journals, bank transactions, VAT returns, stock movements'.($this->option('keep-expenses') ? '' : ', and expenses').'. Continue?',
            false,
        )) {
            $this->warn('Aborted.');

            return self::SUCCESS;
        }

        $tables = $this->transactionTables();
        $beforeCounts = $this->countsFor($tables);

        DB::transaction(function () use ($tables, $stock): void {
            foreach ($tables as $table) {
                $this->deleteTable($table);
            }

            $this->resetProductStock($stock);
        });

        $this->resetAutoIncrement($tables);

        $this->components->info('Transaction data cleared.');
        $this->table(
            ['Table', 'Deleted rows'],
            collect($beforeCounts)
                ->map(fn (int $count, string $table): array => [$table, $count])
                ->values()
                ->all(),
        );
        $this->info('Product opening stock reset to '.rtrim(rtrim(number_format($stock, 3, '.', ''), '0'), '.').' for non-service product items.');

        return self::SUCCESS;
    }

    /**
     * Child tables must appear before parent tables.
     *
     * @return array<int, string>
     */
    private function transactionTables(): array
    {
        return array_values(array_filter([
            'voucher_allocations',
            'sales_return_sales_invoice',
            'sales_return_items',
            'sales_returns',
            'sales_invoice_items',
            'sales_invoices',
            'purchase_invoice_items',
            'purchase_invoices',
            $this->option('keep-expenses') ? null : 'expenses',
            'vouchers',
            'bank_transactions',
            'stock_movements',
            'vat_returns',
            'journal_lines',
            'journal_entries',
        ]));
    }

    /**
     * @param  array<int, string>  $tables
     * @return array<string, int>
     */
    private function countsFor(array $tables): array
    {
        $counts = [];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }

    private function deleteTable(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        DB::table($table)->delete();
    }

    private function resetProductStock(float $stock): void
    {
        if (! Schema::hasTable('product_items')) {
            return;
        }

        DB::table('product_items')
            ->where(function ($query): void {
                $query->whereNull('product_type')
                    ->orWhere('product_type', '!=', 'service');
            })
            ->update([
                'opening_stock' => $stock,
                'stock_enabled' => true,
                'updated_at' => now(),
            ]);
    }

    /**
     * @param  array<int, string>  $tables
     */
    private function resetAutoIncrement(array $tables): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
        }
    }
}
