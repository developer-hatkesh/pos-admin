<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Filament\Imports\ProductItemImporter;
use App\Models\Company;
use App\Models\ProductItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;
use SplFileObject;

class ExportProductImportCsv extends Command
{
    protected $signature = 'products:export-import-csv
        {--company= : Required company ID}
        {--file= : Output CSV path; defaults to storage/app/exports}';

    protected $description = 'Export one company product catalogue using the exact ProductItemImporter CSV columns.';

    public function handle(): int
    {
        $companyId = filter_var($this->option('company'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $company = $companyId ? Company::query()->find($companyId) : null;

        if (! $company) {
            $this->error('A valid --company ID is required.');

            return self::FAILURE;
        }

        $path = filled($this->option('file'))
            ? base_path((string) $this->option('file'))
            : storage_path("app/exports/products-import-company-{$company->id}.csv");

        File::ensureDirectoryExists(dirname($path));

        $csv = new SplFileObject($path, 'w');
        $csv->setCsvControl(',', '"', '\\');
        $columns = collect(ProductItemImporter::getColumns())->map->getName()->all();
        $csv->fputcsv($columns);

        $count = 0;
        ProductItem::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->with(['category', 'brand', 'taxRate'])
            ->orderBy('id')
            ->chunkById(200, function ($products) use ($csv, $columns, &$count): void {
                foreach ($products as $product) {
                    $row = [
                        'item_code' => $product->item_code,
                        'name' => $product->name,
                        'product_type' => $product->product_type?->value ?? (string) $product->product_type,
                        'category' => $product->category?->name,
                        'brand' => $product->brand?->name,
                        'barcode' => $product->barcode,
                        'sku' => $product->sku,
                        'unit' => $product->unit?->value ?? (string) $product->unit,
                        'purchase_price' => number_format((float) $product->purchase_price, 2, '.', ''),
                        'sale_price' => number_format((float) $product->sale_price, 2, '.', ''),
                        'wholesale_price' => number_format((float) $product->wholesale_price, 2, '.', ''),
                        'tax_rate' => $product->taxRate?->name ?? number_format((float) $product->vat_rate, 2, '.', ''),
                        'tax_type' => $product->tax_type?->value ?? (string) $product->tax_type,
                        'stock_enabled' => $product->stock_enabled ? 'yes' : 'no',
                        'opening_stock' => number_format((float) $product->current_stock, 3, '.', ''),
                        'stock_alert_qty' => $product->stock_alert_qty === null ? null : number_format((float) $product->stock_alert_qty, 3, '.', ''),
                        'expiry_date' => $product->expiry_date?->format('Y-m-d'),
                        'description' => $product->description,
                        'status' => $product->status?->value ?? (string) $product->status,
                    ];

                    $ordered = collect($columns)->map(fn (string $column): mixed => $row[$column] ?? null)->all();

                    if ($csv->fputcsv($ordered) === false) {
                        throw new RuntimeException('Unable to write the product export CSV.');
                    }

                    $count++;
                }
            });

        $this->info("Exported {$count} products for {$company->name} (ID {$company->id}).");
        $this->line($path);

        return self::SUCCESS;
    }
}
