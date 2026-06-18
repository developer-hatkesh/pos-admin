<?php

declare(strict_types=1);

namespace App\Filament\Resources\Items;

use App\Enums\ItemUnit;
use App\Enums\ProductType;
use App\Enums\Status;
use App\Enums\TaxType;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\Items\Pages\CreateItem;
use App\Filament\Resources\Items\Pages\EditItem;
use App\Filament\Resources\Items\Pages\ListItems;
use App\Models\ProductItem;
use App\Models\VariationType;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class ItemResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = ProductItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Product Item';

    protected static ?string $pluralModelLabel = 'Product Items';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Product Meta')->schema([
                self::companySelect(),
                TextInput::make('name')->label('Product name')->required()->maxLength(255),
                TextInput::make('product_code')->label('Product ID')->maxLength(255),
                TextInput::make('item_code')->label('Item code')->maxLength(255),
                Select::make('category_id')->relationship('category', 'name')->searchable()->preload(),
                Select::make('brand_id')->relationship('brand', 'name')->searchable()->preload(),
                TextInput::make('barcode')->label('Barcode')->maxLength(255),
                TextInput::make('sku')->label('SKU')->maxLength(255),
                Select::make('unit')->options(ItemUnit::class)->default(ItemUnit::Pieces)->required(),
                Select::make('status')->options(Status::class)->default(Status::Active)->required(),
                Textarea::make('description')->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),

            Section::make('Pricing Info')->schema([
                self::moneyInput('purchase_price')
                    ->required(fn (Get $get): bool => $get('product_type') !== ProductType::Service->value)
                    ->hidden(fn (Get $get): bool => $get('product_type') === ProductType::Service->value),
                self::moneyInput('sale_price')->required(),
                TextInput::make('vat_rate')->numeric()->default(20)->step('0.01')->required(),
                Select::make('tax_type')->options(TaxType::options())->default(TaxType::Exclusive->value)->required(),
            ])->columns(2)->columnSpanFull(),

            Section::make('Stock And Availability')->schema([
                Toggle::make('stock_enabled')
                    ->default(true)
                    ->disabled(fn (Get $get): bool => $get('product_type') === ProductType::Service->value)
                    ->dehydrated(),
                TextInput::make('opening_stock')
                    ->numeric()
                    ->default(0)
                    ->step('0.001')
                    ->required(fn (Get $get): bool => $get('product_type') !== ProductType::Service->value)
                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                    ->dehydrated(fn (string $operation): bool => $operation === 'create'),
                Placeholder::make('current_stock')
                    ->label('Current stock')
                    ->content(fn (?ProductItem $record): string => $record ? number_format($record->current_stock, 3) : 'Auto calculated after creation'),
                TextInput::make('stock_alert_qty')
                    ->label('Stock alert qty')
                    ->numeric()
                    ->step('0.001')
                    ->visible(fn (Get $get): bool => $get('product_type') !== ProductType::Service->value),
                DatePicker::make('expiry_date')
                    ->visible(fn (Get $get): bool => $get('product_type') !== ProductType::Service->value),
            ])->columns(2)->visible(fn (Get $get): bool => $get('product_type') === ProductType::Single->value)->columnSpanFull(),

            Section::make('Product Images')->schema([
                FileUpload::make('product_images')
                    ->label('Product images')
                    ->disk('public')
                    ->directory(fn (?ProductItem $record): string => $record === null ? 'products/tmp' : "products/{$record->getKey()}/incoming")
                    ->image()
                    ->multiple()
                    ->reorderable()
                    ->openable()
                    ->downloadable()
                    ->maxSize(10240)
                    ->deleteUploadedFileUsing(fn (): null => null)
                    ->columnSpanFull(),
            ])->columnSpanFull(),

            Section::make('Product Type And Variations')->schema([
                Select::make('product_type')
                    ->options(ProductType::options())
                    ->default(ProductType::Single->value)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                        if ($state === ProductType::Service->value) {
                            $set('stock_enabled', false);
                            $set('opening_stock', 0);
                            $set('stock_alert_qty', null);
                            $set('expiry_date', null);
                        }

                        if ($state !== ProductType::Variation->value) {
                            $set('parent_product_item_id', null);
                            $set('variation_id', null);
                            $set('variation_type_id', null);
                            $set('variation_items', []);
                        }
                    }),
                Select::make('variation_id')
                    ->label('Variation name')
                    ->relationship('variation', 'name')
                    ->searchable()
                    ->preload()
                    ->required(fn (Get $get): bool => $get('product_type') === ProductType::Variation->value)
                    ->visible(fn (Get $get): bool => $get('product_type') === ProductType::Variation->value)
                    ->live()
                    ->afterStateUpdated(fn (Set $set, Get $get, ?int $state): null => $set('variation_items', self::variationRowsFor($state, null, [
                        'purchase_price' => $get('purchase_price') ?? 0,
                        'sale_price' => $get('sale_price') ?? 0,
                    ]))),
                Repeater::make('variation_items')
                    ->label('Variation items')
                    ->schema([
                        Hidden::make('id'),
                        Hidden::make('variation_type_id'),
                        Placeholder::make('variation_value')
                            ->label('Variation value')
                            ->content(fn (Get $get): string => VariationType::query()->find($get('variation_type_id'))?->name ?? 'Option'),
                        TextInput::make('sku')->label('SKU')->maxLength(255),
                        TextInput::make('barcode')->label('Barcode')->maxLength(255),
                        self::moneyInput('purchase_price')->required(),
                        self::moneyInput('sale_price')->required(),
                        TextInput::make('opening_stock')
                            ->numeric()
                            ->default(0)
                            ->step('0.001')
                            ->disabled(fn (Get $get): bool => filled($get('id')))
                            ->dehydrated(),
                        TextInput::make('stock_alert_qty')->label('Alert qty')->numeric()->step('0.001'),
                        Select::make('status')->options(Status::class)->default(Status::Active->value)->required(),
                    ])
                    ->columns(4)
                    ->reorderable(false)
                    ->addable(false)
                    ->deletable(false)
                    ->visible(fn (Get $get): bool => $get('product_type') === ProductType::Variation->value)
                    ->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('first_product_image_url')->label('Image')->disk('public')->square(),
                TextColumn::make('category.name')->searchable()->sortable(),
                TextColumn::make('brand.name')->searchable()->sortable(),
                TextColumn::make('product_code')->label('Product ID')->searchable()->sortable(),
                TextColumn::make('item_code')->searchable()->sortable(),
                TextColumn::make('barcode')->searchable()->sortable(),
                TextColumn::make('sku')->label('SKU')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('product_type')->badge()->sortable(),
                TextColumn::make('variation.name')->label('Variation')->searchable()->sortable(),
                TextColumn::make('variationType.name')->label('Value')->searchable()->sortable(),
                TextColumn::make('unit')->sortable(),
                TextColumn::make('sale_price')->money('GBP')->sortable(),
                TextColumn::make('vat_rate')->suffix('%')->sortable(),
                IconColumn::make('stock_enabled')->boolean(),
                TextColumn::make('current_stock')->numeric(decimalPlaces: 3),
                TextColumn::make('stock_alert_qty')->label('Alert')->numeric(decimalPlaces: 3)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')->badge()->sortable(),
            ])
            ->filters([
                self::statusFilter(Status::class),
                SelectFilter::make('product_type')->options(ProductType::options()),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListItems::route('/'),
            'create' => CreateItem::route('/create'),
            'edit' => EditItem::route('/{record}/edit'),
        ];
    }

    public static function createRecordWithProductImages(array $data): ProductItem
    {
        $imagePaths = self::pullProductImagePaths($data);
        $variationRows = self::pullVariationRows($data);
        self::normalizeProductData($data);

        $record = ProductItem::create($data);

        self::syncProductImages($record, $imagePaths);
        self::syncVariationItems($record, $variationRows);

        return $record;
    }

    public static function updateRecordWithProductImages(ProductItem $record, array $data): void
    {
        $imagePaths = self::pullProductImagePaths($data);
        $variationRows = self::pullVariationRows($data);
        unset($data['opening_stock']);
        self::normalizeProductData($data, $record);

        $record->update($data);
        self::syncProductImages($record, $imagePaths);
        self::syncVariationItems($record, $variationRows);
    }

    public static function variationRowsFor(?int $variationId, ?ProductItem $record = null, array $defaults = []): array
    {
        if (! $variationId) {
            return [];
        }

        $existingRows = $record?->variationChildren()
            ->where('variation_id', $variationId)
            ->get()
            ->keyBy('variation_type_id') ?? collect();

        return VariationType::query()
            ->where('variation_id', $variationId)
            ->orderBy('name')
            ->get()
            ->map(function (VariationType $variationType) use ($existingRows, $record): array {
                /** @var ProductItem|null $existing */
                $existing = $existingRows->get($variationType->id);

                return [
                    'id' => $existing?->id,
                    'variation_type_id' => $variationType->id,
                    'sku' => $existing?->sku,
                    'barcode' => $existing?->barcode,
                    'purchase_price' => $existing?->purchase_price ?? $record?->purchase_price ?? $defaults['purchase_price'] ?? 0,
                    'sale_price' => $existing?->sale_price ?? $record?->sale_price ?? $defaults['sale_price'] ?? 0,
                    'opening_stock' => $existing?->opening_stock ?? 0,
                    'stock_alert_qty' => $existing?->stock_alert_qty,
                    'status' => ($existing?->status ?? Status::Active)->value,
                ];
            })
            ->all();
    }

    private static function pullProductImagePaths(array &$data): array
    {
        $imagePaths = Arr::wrap($data['product_images'] ?? []);

        unset($data['product_images']);

        return array_values(array_filter($imagePaths, is_string(...)));
    }

    private static function pullVariationRows(array &$data): array
    {
        $variationRows = Arr::wrap($data['variation_items'] ?? []);

        unset($data['variation_items']);

        return array_values($variationRows);
    }

    private static function normalizeProductData(array &$data, ?ProductItem $record = null): void
    {
        $type = ProductType::tryFrom((string) ($data['product_type'] ?? $record?->product_type?->value ?? ProductType::Single->value));

        if ($type === ProductType::Service) {
            $data['stock_enabled'] = false;
            $data['opening_stock'] = 0;
            $data['stock_alert_qty'] = null;
            $data['expiry_date'] = null;
        }

        if ($type === ProductType::Variation) {
            $data['stock_enabled'] = false;
            $data['opening_stock'] = 0;
            $data['stock_alert_qty'] = null;
            $data['expiry_date'] = null;
            $data['parent_product_item_id'] = null;
            $data['variation_type_id'] = null;
        }
    }

    private static function syncVariationItems(ProductItem $record, array $variationRows): void
    {
        if ($record->product_type !== ProductType::Variation || ! $record->variation_id) {
            return;
        }

        $activeVariationTypeIds = [];

        foreach ($variationRows as $row) {
            $variationTypeId = (int) ($row['variation_type_id'] ?? 0);

            if ($variationTypeId <= 0) {
                continue;
            }

            $activeVariationTypeIds[] = $variationTypeId;
            $variationType = VariationType::query()->find($variationTypeId);

            if (! $variationType) {
                continue;
            }

            $child = ProductItem::query()
                ->withoutGlobalScopes()
                ->where('parent_product_item_id', $record->id)
                ->where('variation_type_id', $variationTypeId)
                ->first();

            $payload = [
                'company_id' => $record->company_id,
                'parent_product_item_id' => $record->id,
                'category_id' => $record->category_id,
                'brand_id' => $record->brand_id,
                'product_type' => ProductType::Variation,
                'variation_id' => $record->variation_id,
                'variation_type_id' => $variationTypeId,
                'product_code' => null,
                'item_code' => $record->item_code,
                'barcode' => $row['barcode'] ?? null,
                'name' => $record->name.' - '.$variationType->name,
                'sku' => $row['sku'] ?? null,
                'description' => $record->description,
                'unit' => $record->unit,
                'purchase_price' => $row['purchase_price'] ?? 0,
                'sale_price' => $row['sale_price'] ?? 0,
                'vat_rate' => $record->vat_rate,
                'tax_type' => $record->tax_type,
                'stock_enabled' => true,
                'stock_alert_qty' => $row['stock_alert_qty'] ?? null,
                'expiry_date' => null,
                'status' => $row['status'] ?? Status::Active->value,
            ];

            if (! $child) {
                $payload['opening_stock'] = $row['opening_stock'] ?? 0;
                ProductItem::query()->create($payload);

                continue;
            }

            unset($payload['opening_stock']);
            $child->update($payload);
        }

        if ($activeVariationTypeIds !== []) {
            ProductItem::query()
                ->withoutGlobalScopes()
                ->where('parent_product_item_id', $record->id)
                ->whereNotIn('variation_type_id', $activeVariationTypeIds)
                ->update(['status' => Status::Inactive->value]);
        }
    }

    private static function syncProductImages(ProductItem $record, array $selectedPaths): void
    {
        $collection = ProductItem::PRODUCT_IMAGES_COLLECTION;
        $selectedPaths = array_values(array_unique($selectedPaths));
        $currentMedia = $record->getMedia($collection);
        $currentPaths = $currentMedia->mapWithKeys(fn ($media): array => [$media->getPathRelativeToRoot() => $media]);

        $currentMedia
            ->reject(fn ($media): bool => in_array($media->getPathRelativeToRoot(), $selectedPaths, true))
            ->each->delete();

        foreach ($selectedPaths as $path) {
            if ($currentPaths->has($path) || ! Storage::disk('public')->exists($path)) {
                continue;
            }

            $record
                ->addMediaFromDisk($path, 'public')
                ->toMediaCollection($collection, 'public');
        }

        $record->refresh();
        $record->syncProductImageUrls();
    }
}
