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
use App\Models\TaxRate;
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
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class ItemResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = ProductItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Product Master';

    protected static ?string $modelLabel = 'Product Item';

    protected static ?string $pluralModelLabel = 'Product Items';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make([
                'default' => 1,
                'xl' => 20,
            ])->schema([
                Grid::make(1)->schema([
                    Section::make('Product Meta')->schema([
                        self::companySelect(),
                        TextInput::make('name')->label('Product name')->required()->maxLength(255),
                        TextInput::make('item_code')->label('Item code')->maxLength(255),
                        Select::make('category_id')->relationship('category', 'name')->searchable()->preload(),
                        Select::make('brand_id')->relationship('brand', 'name')->searchable()->preload(),
                        TextInput::make('barcode')->label('Barcode')->maxLength(255),
                        TextInput::make('sku')->label('SKU')->maxLength(255),
                        Select::make('unit')->options(ItemUnit::class)->default(ItemUnit::Pieces)->required(),
                        Select::make('status')->options(Status::class)->default(Status::Active)->required(),
                        Textarea::make('description')->columnSpanFull(),
                    ])->columns(2),

                    Section::make('Stock And Availability')
                        ->extraAttributes(['class' => 'product-item-stock-section'])
                        ->schema([
                            Grid::make([
                                'default' => 1,
                                'md' => 4,
                            ])->schema([
                                Toggle::make('stock_enabled')
                                    ->label('Track stock')
                                    ->default(true)
                                    ->disabled(fn (Get $get, ?ProductItem $record): bool => self::productTypeValue($get, $record) === ProductType::Service->value)
                                    ->dehydrated()
                                    ->columnSpan([
                                        'default' => 1,
                                        'md' => 1,
                                    ]),
                                TextInput::make('opening_stock')
                                    ->numeric()
                                    ->default(0)
                                    ->step('0.001')
                                    ->required(fn (Get $get, ?ProductItem $record): bool => self::productTypeValue($get, $record) !== ProductType::Service->value)
                                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                                    ->dehydrated(fn (string $operation): bool => $operation === 'create')
                                    ->columnSpan([
                                        'default' => 1,
                                        'md' => 1,
                                    ]),
                                TextInput::make('stock_alert_qty')
                                    ->label('Alert qty')
                                    ->numeric()
                                    ->step('0.001')
                                    ->visible(fn (Get $get, ?ProductItem $record): bool => self::productTypeValue($get, $record) !== ProductType::Service->value)
                                    ->columnSpan([
                                        'default' => 1,
                                        'md' => 1,
                                    ]),
                                DatePicker::make('expiry_date')
                                    ->visible(fn (Get $get, ?ProductItem $record): bool => self::productTypeValue($get, $record) !== ProductType::Service->value)
                                    ->columnSpan([
                                        'default' => 1,
                                        'md' => 1,
                                    ]),
                                Placeholder::make('current_stock')
                                    ->label('Current stock')
                                    ->content(fn (?ProductItem $record): string => $record ? number_format($record->current_stock, 3) : 'Auto calculated after creation')
                                    ->columnSpanFull(),
                            ]),
                        ])
                        ->visible(fn (Get $get, ?ProductItem $record): bool => self::productTypeValue($get, $record) === ProductType::Single->value),
                ])->columnSpan([
                    'default' => 1,
                    'xl' => 13,
                ]),

                Grid::make(1)->schema([
                    Section::make('Pricing Info')->schema([
                        self::moneyInput('purchase_price')
                            ->required(fn (Get $get, ?ProductItem $record): bool => self::productTypeValue($get, $record) !== ProductType::Service->value)
                            ->hidden(fn (Get $get, ?ProductItem $record): bool => self::productTypeValue($get, $record) === ProductType::Service->value),
                        self::moneyInput('sale_price')->label('Retail Price')->required(),
                        self::moneyInput('wholesale_price')->required(),
                        Hidden::make('vat_rate')->default(20),
                        Select::make('tax_rate_id')
                            ->label('VAT rate')
                            ->options(fn (): array => TaxRate::options())
                            ->default(fn (): int => TaxRate::defaultId())
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, ?int $state): mixed => $set('vat_rate', TaxRate::rateFor($state))),
                        Select::make('tax_type')->options(TaxType::options())->default(TaxType::Exclusive->value)->required(),
                    ])->columns(2),

                    Section::make('Product Images')->schema([
                        FileUpload::make('product_images')
                            ->label('Product images')
                            ->disk(fn (): string => self::productImageDisk())
                            ->directory(fn (?ProductItem $record): string => $record === null ? 'products/tmp' : "products/{$record->getKey()}/incoming")
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->openable()
                            ->downloadable()
                            ->maxSize(10240)
                            ->deleteUploadedFileUsing(fn (): null => null)
                            ->columnSpanFull(),
                    ]),
                ])->columnSpan([
                    'default' => 1,
                    'xl' => 7,
                ]),
            ])->columnSpanFull(),

            Section::make('Product Type And Variations')->schema([
                Select::make('product_type')
                    ->options(ProductType::options())
                    ->default(ProductType::Single->value)
                    ->required()
                    ->live()
                    ->visible(fn (string $operation): bool => $operation === 'create')
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
                Placeholder::make('product_type_label')
                    ->label('Product type')
                    ->content(fn (?ProductItem $record): string => $record?->product_type?->label() ?? 'N/A')
                    ->visible(fn (string $operation): bool => $operation === 'edit'),
                Select::make('variation_id')
                    ->label('Variation name')
                    ->relationship('variation', 'name')
                    ->searchable()
                    ->preload()
                    ->required(fn (Get $get, ?ProductItem $record): bool => self::productTypeValue($get, $record) === ProductType::Variation->value)
                    ->visible(fn (Get $get, ?ProductItem $record): bool => self::productTypeValue($get, $record) === ProductType::Variation->value)
                    ->live()
                    ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                        $set('variation_items', self::variationRowsFor($state, null, [
                            'purchase_price' => $get('purchase_price') ?? 0,
                            'sale_price' => $get('sale_price') ?? 0,
                            'wholesale_price' => $get('wholesale_price') ?? 0,
                        ]));
                    }),
                Repeater::make('variation_items')
                    ->label('Variation items')
                    ->table([
                        TableColumn::make('Variation value'),
                        TableColumn::make('SKU'),
                        TableColumn::make('Barcode'),
                        TableColumn::make('Purchase price'),
                        TableColumn::make('Retail Price'),
                        TableColumn::make('Wholesale price'),
                        TableColumn::make('Opening stock'),
                        TableColumn::make('Alert qty'),
                        TableColumn::make('Status'),
                    ])
                    ->schema([
                        Hidden::make('id'),
                        Hidden::make('variation_type_id'),
                        TextInput::make('variation_value')
                            ->hiddenLabel()
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('sku')->hiddenLabel()->maxLength(255),
                        TextInput::make('barcode')->hiddenLabel()->maxLength(255),
                        self::moneyInput('purchase_price')->hiddenLabel()->required(),
                        self::moneyInput('sale_price')->hiddenLabel()->required(),
                        self::moneyInput('wholesale_price')->hiddenLabel()->required(),
                        TextInput::make('opening_stock')
                            ->hiddenLabel()
                            ->numeric()
                            ->default(0)
                            ->step('0.001')
                            ->disabled(fn (Get $get): bool => filled($get('id')))
                            ->dehydrated(),
                        TextInput::make('stock_alert_qty')->hiddenLabel()->numeric()->step('0.001'),
                        Select::make('status')->hiddenLabel()->options(Status::class)->default(Status::Active->value)->required(),
                    ])
                    ->reorderable(false)
                    ->addable(false)
                    ->deletable(false)
                    ->visible(fn (Get $get, ?ProductItem $record): bool => self::productTypeValue($get, $record) === ProductType::Variation->value)
                    ->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('first_product_image_url')->label('Image')->disk('public')->square(),
                TextColumn::make('name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->description(fn (ProductItem $record): string => collect([
                        $record->sku ? 'SKU: '.$record->sku : null,
                        $record->barcode ? 'Barcode: '.$record->barcode : null,
                        $record->item_code ? 'Item: '.$record->item_code : null,
                    ])->filter()->implode(' | ')),
                TextColumn::make('brand.name')
                    ->label('Brand / Category')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (?string $state): string => $state ?: 'No brand')
                    ->description(fn (ProductItem $record): string => $record->category?->name ? 'Category: '.$record->category->name : 'No category'),
                TextColumn::make('product_type')->badge()->sortable(),
                TextColumn::make('sale_price')
                    ->label('Price / VAT')
                    ->formatStateUsing(fn (mixed $state): string => app_money($state))
                    ->sortable()
                    ->description(fn (ProductItem $record): string => 'Wholesale: '.app_money((float) $record->wholesale_price).' | VAT: '.number_format((float) $record->vat_rate, 2).'%'),
                TextColumn::make('current_stock')
                    ->label('Stock')
                    ->numeric(decimalPlaces: 3)
                    ->description(fn (ProductItem $record): string => $record->stock_enabled ? 'Stock enabled' : 'No stock tracking'),
                TextColumn::make('status')->badge()->sortable(),
            ])
            ->filters([
                self::statusFilter(Status::class),
                SelectFilter::make('product_type')->options(ProductType::options()),
                SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('availability')
                    ->label('Availability')
                    ->options([
                        'in_stock' => 'In Stock',
                        'low_stock' => 'Low Stock',
                        'out_of_stock' => 'Out Of Stock',
                        'service' => 'Service / No Stock',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        $currentStockSql = self::currentStockSql();

                        return match ($value) {
                            'in_stock' => $query
                                ->where('stock_enabled', true)
                                ->whereRaw("{$currentStockSql} > 0"),
                            'low_stock' => $query
                                ->where('stock_enabled', true)
                                ->whereNotNull('stock_alert_qty')
                                ->whereRaw("{$currentStockSql} <= stock_alert_qty"),
                            'out_of_stock' => $query
                                ->where('stock_enabled', true)
                                ->whereRaw("{$currentStockSql} <= 0"),
                            'service' => $query->where(function (Builder $query): void {
                                $query->where('product_type', ProductType::Service->value)
                                    ->orWhere('stock_enabled', false);
                            }),
                            default => $query,
                        };
                    }),
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
        unset($data['opening_stock'], $data['product_type']);
        self::normalizeProductData($data, $record);

        $record->update($data);
        self::syncProductImages($record, $imagePaths);
        self::syncVariationItems($record, $variationRows);
    }

    public static function variationRowsFor(mixed $variationId, ?ProductItem $record = null, array $defaults = []): array
    {
        $variationId = filled($variationId) ? (int) $variationId : null;

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
            ->map(function (VariationType $variationType) use ($existingRows, $record, $defaults): array {
                /** @var ProductItem|null $existing */
                $existing = $existingRows->get($variationType->id);

                return [
                    'id' => $existing?->id,
                    'variation_type_id' => $variationType->id,
                    'variation_value' => $variationType->name,
                    'sku' => $existing?->sku,
                    'barcode' => $existing?->barcode,
                    'purchase_price' => $existing?->purchase_price ?? $record?->purchase_price ?? $defaults['purchase_price'] ?? 0,
                    'sale_price' => $existing?->sale_price ?? $record?->sale_price ?? $defaults['sale_price'] ?? 0,
                    'wholesale_price' => $existing?->wholesale_price ?? $record?->wholesale_price ?? $defaults['wholesale_price'] ?? 0,
                    'opening_stock' => $existing?->opening_stock ?? 0,
                    'stock_alert_qty' => $existing?->stock_alert_qty,
                    'status' => ($existing?->status ?? Status::Active)->value,
                ];
            })
            ->all();
    }

    private static function currentStockSql(): string
    {
        $increaseTypes = collect([
            'in',
            'adjustment',
            'purchase',
            'sales_return',
            'adjustment_in',
        ])
            ->map(fn (string $type): string => "'{$type}'")
            ->implode(', ');

        return "(COALESCE(product_items.opening_stock, 0) + COALESCE((
            SELECT SUM(CASE
                WHEN stock_movements.type IN ({$increaseTypes}) THEN stock_movements.quantity
                ELSE -stock_movements.quantity
            END)
            FROM stock_movements
            WHERE stock_movements.product_item_id = product_items.id
        ), 0))";
    }

    private static function productTypeValue(Get $get, ?ProductItem $record = null): string
    {
        $state = $get('product_type');

        if (filled($state)) {
            return (string) $state;
        }

        return $record?->product_type?->value ?? ProductType::Single->value;
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

        if (array_key_exists('tax_rate_id', $data)) {
            $data['vat_rate'] = TaxRate::rateFor(filled($data['tax_rate_id']) ? (int) $data['tax_rate_id'] : null);
        }

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
                'item_code' => $record->item_code,
                'barcode' => $row['barcode'] ?? null,
                'name' => $record->name.' - '.$variationType->name,
                'sku' => $row['sku'] ?? null,
                'description' => $record->description,
                'unit' => $record->unit,
                'purchase_price' => $row['purchase_price'] ?? 0,
                'sale_price' => $row['sale_price'] ?? 0,
                'wholesale_price' => $row['wholesale_price'] ?? 0,
                'vat_rate' => $record->vat_rate,
                'tax_rate_id' => $record->tax_rate_id,
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
            if ($currentPaths->has($path) || ! Storage::disk(self::productImageDisk())->exists($path)) {
                continue;
            }

            $record
                ->addMediaFromDisk($path, self::productImageDisk())
                ->toMediaCollection($collection, self::productImageDisk());
        }

        $record->refresh();
        $record->syncProductImageUrls();
    }

    private static function productImageDisk(): string
    {
        return (string) config('media-library.disk_name', 'public');
    }
}
