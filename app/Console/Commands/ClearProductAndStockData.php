<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClearProductAndStockData extends Command
{
    protected $signature = 'products:clear-data
        {--force : Run without confirmation}
        {--keep-media : Keep media-library records attached to products}';

    protected $description = 'Clear only product master records and stock movements while keeping categories, brands, contacts, invoices, vouchers, expenses, and other data.';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm(
            'This will permanently delete products, legacy items, stock movements, and product media records. Categories, brands, variations, invoices, contacts, vouchers, expenses, and bank data will be kept. Continue?',
            false,
        )) {
            $this->warn('Aborted.');

            return self::SUCCESS;
        }

        $tables = $this->tablesToClear();
        $beforeCounts = $this->countsFor($tables);
        $referenceUpdates = [];

        DB::transaction(function () use ($tables, &$referenceUpdates): void {
            $referenceUpdates = $this->clearProductReferences();

            foreach ($tables as $table) {
                $this->deleteTable($table);
            }
        });

        $this->resetAutoIncrement($tables);

        $this->components->info('Product and stock data cleared.');
        $this->table(
            ['Table', 'Deleted rows'],
            collect($beforeCounts)
                ->map(fn (int $count, string $table): array => [$table, $count])
                ->values()
                ->all(),
        );

        $updatedReferences = collect($referenceUpdates)->filter()->sum();

        if ($updatedReferences > 0) {
            $this->info("Cleared {$updatedReferences} product references from kept transaction rows.");
        }

        $this->line('Kept categories, brands, variations, invoices, contacts, vouchers, expenses, bank data, companies, and users.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function tablesToClear(): array
    {
        return array_values(array_filter([
            $this->option('keep-media') ? null : 'media',
            'stock_movements',
            'product_items',
            'items',
        ]));
    }

    /**
     * Product references on kept transaction rows are nulled so those rows do not
     * point at deleted product IDs on MyISAM databases where FKs are not enforced.
     *
     * @return array<string, int>
     */
    private function clearProductReferences(): array
    {
        $updates = [];

        foreach ($this->productReferenceColumns() as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $payload = [];

            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    $payload[$column] = null;
                }
            }

            if ($payload === []) {
                continue;
            }

            $updates[$table] = DB::table($table)
                ->where(function ($query) use ($payload): void {
                    foreach (array_keys($payload) as $column) {
                        $query->orWhereNotNull($column);
                    }
                })
                ->update($payload);
        }

        return $updates;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function productReferenceColumns(): array
    {
        return [
            'sales_invoice_items' => ['product_item_id', 'item_id'],
            'purchase_invoice_items' => ['product_item_id', 'item_id'],
            'sales_return_items' => ['product_item_id'],
            'purchase_return_items' => ['product_item_id'],
        ];
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

            $query = DB::table($table);

            if ($table === 'media' && Schema::hasColumn('media', 'model_type')) {
                $query->whereIn('model_type', [
                    'App\\Models\\ProductItem',
                    'App\\Models\\Item',
                ]);
            }

            $counts[$table] = $query->count();
        }

        return $counts;
    }

    private function deleteTable(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $query = DB::table($table);

        if ($table === 'media' && Schema::hasColumn('media', 'model_type')) {
            $query->whereIn('model_type', [
                'App\\Models\\ProductItem',
                'App\\Models\\Item',
            ]);
        }

        $query->delete();
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
