<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ItemUnit;
use App\Enums\Status;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\ProductItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ImportProductPdfData extends Command
{
    protected $signature = 'products:import-pdf-data
        {--company= : Company ID to import products into}
        {--file=database/data/product_pdf_import.json : JSON file generated from the product PDFs}';

    protected $description = 'Import product names and prices extracted from the supplied product PDFs.';

    public function handle(): int
    {
        $company = $this->resolveCompany();
        $path = base_path((string) $this->option('file'));

        if (! is_file($path)) {
            throw new RuntimeException("Import file not found: {$path}");
        }

        $rows = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, $company, &$created, &$updated): void {
            foreach ($rows as $row) {
                $brand = $this->brandFor($company, (string) $row['name']);
                $category = $this->categoryFor($company, (string) $row['name']);
                $itemCode = $this->itemCode((string) $row['source'], (int) $row['source_no']);

                $product = ProductItem::query()
                    ->withoutGlobalScope('company')
                    ->firstOrNew([
                        'company_id' => $company->id,
                        'item_code' => $itemCode,
                    ]);

                $product->fill([
                    'category_id' => $category->id,
                    'brand_id' => $brand->id,
                    'name' => Str::headline((string) $row['name']),
                    'description' => sprintf('Imported from %s row %s.', $row['source'], $row['source_no']),
                    'unit' => ItemUnit::Bottle,
                    'purchase_price' => 0,
                    'sale_price' => (float) $row['price'],
                    'vat_rate' => 20,
                    'stock_enabled' => true,
                    'opening_stock' => 0,
                    'status' => Status::Active,
                ]);

                $product->exists ? $updated++ : $created++;
                $product->save();
            }
        });

        $this->info("Imported {$created} new products and updated {$updated} products for {$company->name}.");

        return self::SUCCESS;
    }

    private function resolveCompany(): Company
    {
        $companyId = $this->option('company');

        if ($companyId !== null) {
            return Company::query()->findOrFail($companyId);
        }

        return Company::query()->firstOrFail();
    }

    private function brandFor(Company $company, string $name): Brand
    {
        $upper = Str::upper($name);

        $brand = match (true) {
            str_contains($upper, 'LATTAFA'),
            str_contains($upper, 'YARA'),
            str_contains($upper, 'ASAD'),
            str_contains($upper, 'KHAMRAH'),
            str_contains($upper, 'BADEE AL OUD'),
            str_contains($upper, 'FAKHAR'),
            str_contains($upper, 'QAED AL FURSAN'),
            str_contains($upper, 'ANA ABIYADH'),
            str_contains($upper, 'RAVE NOW') => 'Lattafa',

            str_contains($upper, 'AFNAN'),
            str_contains($upper, 'SUPERMACY'),
            str_contains($upper, 'SUPERMECY'),
            str_contains($upper, 'SUPREMACY') => 'Afnan',

            str_contains($upper, 'ORIENTICA') => 'Orientica',
            str_contains($upper, 'AL-REHAB'),
            str_contains($upper, 'AL -REHAB'),
            str_contains($upper, 'AL REHAB') => 'Al-Rehab',
            str_contains($upper, 'CLUB DE NUIT') => 'Armaf',
            str_contains($upper, 'AMBER OUD') => 'Al Haramain',
            str_contains($upper, 'SHAGHAF') => 'Swiss Arabian',
            str_contains($upper, 'HAWAS') => 'Rasasi',
            str_contains($upper, 'KHADLAJ') => 'Khadlaj',
            str_contains($upper, 'KHALIS') => 'Khalis',
            str_contains($upper, 'CARAD') => 'Carad',
            str_contains($upper, 'ARD') => 'Ard Al Zaafaran',
            default => 'Assorted',
        };

        return $this->firstOrCreateBrand($company, $brand);
    }

    private function categoryFor(Company $company, string $name): Category
    {
        $upper = Str::upper($name);

        $category = match (true) {
            str_contains($upper, 'ROLLON'),
            str_contains($upper, 'ROLL ON'),
            str_contains($upper, '6ML') => 'Perfume Oil',
            str_contains($upper, 'SPRAY') => 'Body Spray',
            str_contains($upper, 'DEO') => 'Gift Set',
            str_contains($upper, 'EDP'),
            str_contains($upper, 'PERFUME') => 'Eau de Parfum',
            default => 'Eau de Parfum',
        };

        return $this->firstOrCreateCategory($company, $category);
    }

    private function itemCode(string $source, int $sourceNo): string
    {
        $prefix = str_contains(Str::lower($source), 'second') ? 'PDF-SECOND' : 'PDF-ASDF';

        return $prefix.'-'.str_pad((string) $sourceNo, 3, '0', STR_PAD_LEFT);
    }

    private function firstOrCreateBrand(Company $company, string $name): Brand
    {
        return Brand::query()->withoutGlobalScope('company')->firstOrCreate(
            ['company_id' => $company->id, 'name' => $name],
            ['status' => Status::Active],
        );
    }

    private function firstOrCreateCategory(Company $company, string $name): Category
    {
        return Category::query()->withoutGlobalScope('company')->firstOrCreate(
            ['company_id' => $company->id, 'name' => $name],
            ['status' => Status::Active],
        );
    }
}
