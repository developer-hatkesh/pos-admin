<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ClearPosTransactionData extends Command
{
    protected $signature = 'transactions:clear-pos-data
        {--company= : Required company ID}
        {--dry-run : Show company-specific deletion counts without changing data}
        {--force : Run without confirmation}
        {--keep-expenses : Keep expense entries}
        {--keep-contacts : Keep customer, supplier, and legacy party records}
        {--keep-bank-accounts : Keep bank account records}';

    protected $description = 'Clear transaction and product catalogue data for one company only.';

    public function handle(): int
    {
        $companyId = filter_var($this->option('company'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $company = $companyId ? Company::query()->find($companyId) : null;

        if (! $company) {
            $this->error('A valid --company ID is required. No data was changed.');

            return self::FAILURE;
        }

        $queries = $this->deletionQueries((int) $companyId);
        $counts = collect($queries)->map(fn (Builder $query): int => (clone $query)->count())->all();

        $this->components->info("Company: {$company->name} (ID {$company->id})");
        $this->displayCounts($counts);

        if ($this->option('dry-run')) {
            $this->info('Dry run only: no records were changed.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm(
            "Permanently clear the listed data for {$company->name} (ID {$company->id}) only?",
            false,
        )) {
            $this->warn('Aborted. No records were changed.');

            return self::SUCCESS;
        }

        $mediaIds = isset($queries['media']) ? (clone $queries['media'])->pluck('id')->all() : [];

        DB::transaction(function () use ($queries): void {
            foreach ($queries as $table => $query) {
                if ($table === 'media') {
                    continue;
                }

                $query->delete();
            }
        });

        Media::query()->whereIn('id', $mediaIds)->get()->each->delete();

        $this->components->info("Transaction data cleared for {$company->name} (ID {$company->id}) only.");
        $this->info('This company product catalogue, categories, brands, variations, product media, stock, and stock history were deleted.');
        $this->line('Other companies, global lookup data, kept bank accounts, and chart-of-account ledgers were not changed.');

        return self::SUCCESS;
    }

    /** @return array<string, Builder> */
    private function deletionQueries(int $companyId): array
    {
        $queries = [];
        $parentIds = fn (string $table): array => Schema::hasTable($table)
            ? DB::table($table)->where('company_id', $companyId)->pluck('id')->map(fn ($id): int => (int) $id)->all()
            : [];
        $ids = [
            'journal_vouchers' => $parentIds('journal_vouchers'),
            'vouchers' => $parentIds('vouchers'),
            'sales_returns' => $parentIds('sales_returns'),
            'purchase_returns' => $parentIds('purchase_returns'),
            'sales_invoices' => $parentIds('sales_invoices'),
            'purchase_invoices' => $parentIds('purchase_invoices'),
            'estimates' => $parentIds('estimates'),
            'journal_entries' => $parentIds('journal_entries'),
            'product_items' => $parentIds('product_items'),
            'items' => $parentIds('items'),
            'variations' => $parentIds('variations'),
        ];

        $this->addChildQuery($queries, 'journal_voucher_allocations', 'journal_voucher_id', $ids['journal_vouchers']);
        $this->addChildQuery($queries, 'voucher_allocations', 'voucher_id', $ids['vouchers']);
        $this->addChildQuery($queries, 'sales_return_sales_invoice', 'sales_return_id', $ids['sales_returns']);
        $this->addChildQuery($queries, 'sales_return_items', 'sales_return_id', $ids['sales_returns']);
        $this->addChildQuery($queries, 'purchase_return_items', 'purchase_return_id', $ids['purchase_returns']);
        $this->addChildQuery($queries, 'sales_invoice_items', 'invoice_id', $ids['sales_invoices']);
        $this->addChildQuery($queries, 'purchase_invoice_items', 'invoice_id', $ids['purchase_invoices']);
        $this->addChildQuery($queries, 'estimate_items', 'estimate_id', $ids['estimates']);
        $this->addChildQuery($queries, 'journal_lines', 'journal_id', $ids['journal_entries']);
        $this->addChildQuery($queries, 'variation_types', 'variation_id', $ids['variations']);

        if (Schema::hasTable('media')) {
            $queries['media'] = DB::table('media')->where(function (Builder $query) use ($ids): void {
                $query->where(function (Builder $query) use ($ids): void {
                    $query->where('model_type', 'App\\Models\\ProductItem')->whereIn('model_id', $ids['product_items']);
                })->orWhere(function (Builder $query) use ($ids): void {
                    $query->where('model_type', 'App\\Models\\Item')->whereIn('model_id', $ids['items']);
                });
            });
        }

        foreach (array_filter([
            'journal_vouchers', 'sales_returns', 'purchase_returns', 'estimates', 'sales_invoices', 'purchase_invoices',
            $this->option('keep-expenses') ? null : 'expenses',
            'contracts', 'incomes', 'vouchers', 'bank_transactions', 'stock_movements', 'vat_returns', 'journal_entries',
            'product_items', 'items', 'categories', 'brands', 'variations',
            $this->option('keep-contacts') ? null : 'customers',
            $this->option('keep-contacts') ? null : 'suppliers',
            $this->option('keep-contacts') ? null : 'parties',
            $this->option('keep-bank-accounts') ? null : 'bank_accounts',
        ]) as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'company_id')) {
                $queries[$table] = DB::table($table)->where('company_id', $companyId);
            }
        }

        return $queries;
    }

    /** @param array<string, Builder> $queries @param array<int, int> $ids */
    private function addChildQuery(array &$queries, string $table, string $foreignKey, array $ids): void
    {
        if (Schema::hasTable($table) && Schema::hasColumn($table, $foreignKey)) {
            $queries[$table] = DB::table($table)->whereIn($foreignKey, $ids);
        }
    }

    /** @param array<string, int> $counts */
    private function displayCounts(array $counts): void
    {
        $this->table(['Table', 'Rows'], collect($counts)->map(fn (int $count, string $table): array => [$table, $count])->values()->all());
        $this->line('Total rows: '.array_sum($counts));
    }
}
