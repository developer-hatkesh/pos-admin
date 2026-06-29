<?php

declare(strict_types=1);

namespace App\Filament\Imports;

use App\Enums\ItemUnit;
use App\Enums\ProductType;
use App\Enums\Status;
use App\Enums\TaxType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ProductItem;
use App\Models\TaxRate;
use App\Support\CurrentCompany;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;
use Illuminate\Validation\Rule;

class ProductItemImporter extends Importer
{
    protected static ?string $model = ProductItem::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('item_code')
                ->label('Item code')
                ->example('PRD-001')
                ->rules(['nullable', 'string', 'max:255'])
                ->ignoreBlankState(),
            ImportColumn::make('name')
                ->label('Product name')
                ->example('Premium Coffee Beans 1kg')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),
            ImportColumn::make('product_type')
                ->label('Product type')
                ->example(ProductType::Single->value)
                ->rules(['nullable', Rule::in([ProductType::Single->value, ProductType::Service->value])])
                ->helperText('Use single or service. Variation products must be created manually.'),
            ImportColumn::make('category')
                ->label('Category')
                ->example('Beverages')
                ->rules(['nullable', 'string', 'max:255'])
                ->fillRecordUsing(fn (ProductItem $record, ?string $state): mixed => $record->category_id = self::categoryIdFor((int) $record->company_id, $state)),
            ImportColumn::make('brand')
                ->label('Brand')
                ->example('House Brand')
                ->rules(['nullable', 'string', 'max:255'])
                ->fillRecordUsing(fn (ProductItem $record, ?string $state): mixed => $record->brand_id = self::brandIdFor((int) $record->company_id, $state)),
            ImportColumn::make('barcode')
                ->label('Barcode')
                ->example('5060000000012')
                ->rules(['nullable', 'string', 'max:255'])
                ->ignoreBlankState(),
            ImportColumn::make('sku')
                ->label('SKU')
                ->example('COFFEE-1KG')
                ->rules(['nullable', 'string', 'max:255'])
                ->ignoreBlankState(),
            ImportColumn::make('unit')
                ->label('Unit')
                ->example(ItemUnit::Pieces->value)
                ->rules(['nullable', Rule::enum(ItemUnit::class)]),
            ImportColumn::make('purchase_price')
                ->label('Purchase price')
                ->numeric(decimalPlaces: 2)
                ->example('7.50')
                ->rules(['nullable', 'numeric', 'min:0']),
            ImportColumn::make('sale_price')
                ->label('Retail price')
                ->numeric(decimalPlaces: 2)
                ->example('12.99')
                ->requiredMapping()
                ->rules(['required', 'numeric', 'min:0']),
            ImportColumn::make('wholesale_price')
                ->label('Wholesale price')
                ->numeric(decimalPlaces: 2)
                ->example('10.50')
                ->requiredMapping()
                ->rules(['required', 'numeric', 'min:0']),
            ImportColumn::make('tax_rate')
                ->label('VAT rate')
                ->example('Standard')
                ->rules(['nullable', 'string', 'max:255'])
                ->helperText('Use tax rate name, ID, or percentage such as 20.')
                ->fillRecordUsing(fn (ProductItem $record, mixed $state): mixed => self::fillTaxRate($record, $state)),
            ImportColumn::make('tax_type')
                ->label('Tax type')
                ->example(TaxType::Exclusive->value)
                ->rules(['nullable', Rule::enum(TaxType::class)]),
            ImportColumn::make('stock_enabled')
                ->label('Track stock')
                ->boolean()
                ->example('yes')
                ->rules(['nullable', 'boolean']),
            ImportColumn::make('opening_stock')
                ->label('Opening stock')
                ->numeric(decimalPlaces: 3)
                ->example('25')
                ->rules(['nullable', 'numeric', 'min:0'])
                ->ignoreBlankState(),
            ImportColumn::make('stock_alert_qty')
                ->label('Alert qty')
                ->numeric(decimalPlaces: 3)
                ->example('5')
                ->rules(['nullable', 'numeric', 'min:0'])
                ->ignoreBlankState(),
            ImportColumn::make('expiry_date')
                ->label('Expiry date')
                ->example('2026-12-31')
                ->rules(['nullable', 'date'])
                ->ignoreBlankState(),
            ImportColumn::make('description')
                ->label('Description')
                ->example('Whole bean coffee for retail sale')
                ->rules(['nullable', 'string']),
            ImportColumn::make('status')
                ->label('Status')
                ->example(Status::Active->value)
                ->rules(['nullable', Rule::enum(Status::class)]),
        ];
    }

    public function resolveRecord(): ?Model
    {
        $this->data['company_id'] = $this->companyId();

        $itemCode = trim((string) ($this->data['item_code'] ?? ''));

        if ($itemCode !== '') {
            $record = ProductItem::query()
                ->where('company_id', $this->companyId())
                ->where('item_code', $itemCode)
                ->first();

            if ($record) {
                return $record;
            }
        }

        $sku = trim((string) ($this->data['sku'] ?? ''));

        if ($sku !== '') {
            $record = ProductItem::query()
                ->where('company_id', $this->companyId())
                ->where('sku', $sku)
                ->first();

            if ($record) {
                return $record;
            }
        }

        return new ProductItem([
            'company_id' => $this->companyId(),
        ]);
    }

    protected function beforeValidate(): void
    {
        $this->data['product_type'] = filled($this->data['product_type'] ?? null)
            ? strtolower((string) $this->data['product_type'])
            : ProductType::Single->value;

        if ($this->data['product_type'] === ProductType::Variation->value) {
            throw new RowImportFailedException('Variation products cannot be imported from this CSV. Create variations from the Product Master form.');
        }

        $this->data['unit'] = filled($this->data['unit'] ?? null) ? strtolower((string) $this->data['unit']) : ItemUnit::Pieces->value;
        $this->data['tax_type'] = filled($this->data['tax_type'] ?? null) ? strtolower((string) $this->data['tax_type']) : TaxType::Exclusive->value;
        $this->data['status'] = filled($this->data['status'] ?? null) ? strtolower((string) $this->data['status']) : Status::Active->value;
    }

    protected function beforeSave(): void
    {
        $this->record->company_id = $this->companyId();
        $this->record->purchase_price ??= 0;
        $this->record->sale_price ??= 0;
        $this->record->wholesale_price ??= 0;
        $this->record->tax_rate_id ??= TaxRate::defaultId();
        $this->record->vat_rate = TaxRate::rateFor((int) $this->record->tax_rate_id);
        $this->record->product_type ??= ProductType::Single;
        $this->record->unit ??= ItemUnit::Pieces;
        $this->record->tax_type ??= TaxType::Exclusive;
        $this->record->status ??= Status::Active;
        $this->record->stock_enabled ??= true;
        $this->record->opening_stock ??= 0;

        if ($this->record->product_type === ProductType::Service) {
            $this->record->stock_enabled = false;
            $this->record->opening_stock = 0;
            $this->record->stock_alert_qty = null;
            $this->record->expiry_date = null;
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = Number::format($import->successful_rows).' '.str('product item')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }

    private function companyId(): int
    {
        return (int) ($this->options['company_id'] ?? app(CurrentCompany::class)->id());
    }

    private static function categoryIdFor(int $companyId, ?string $name): ?int
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        return Category::query()->firstOrCreate([
            'company_id' => $companyId,
            'name' => $name,
        ], [
            'status' => Status::Active,
        ])->id;
    }

    private static function brandIdFor(int $companyId, ?string $name): ?int
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        return Brand::query()->firstOrCreate([
            'company_id' => $companyId,
            'name' => $name,
        ], [
            'status' => Status::Active,
        ])->id;
    }

    private static function fillTaxRate(ProductItem $record, mixed $state): void
    {
        $state = trim((string) $state);
        $taxRate = null;

        if ($state !== '') {
            $taxRate = TaxRate::query()
                ->whereKey(is_numeric($state) && str_contains($state, '.') === false ? (int) $state : null)
                ->orWhere('name', $state)
                ->orWhere('rate', round((float) $state, 2))
                ->first();
        }

        $record->tax_rate_id = $taxRate?->id ?? TaxRate::defaultId();
        $record->vat_rate = TaxRate::rateFor((int) $record->tax_rate_id);
    }
}
