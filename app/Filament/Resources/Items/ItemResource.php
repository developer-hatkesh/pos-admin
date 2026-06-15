<?php

declare(strict_types=1);

namespace App\Filament\Resources\Items;

use App\Enums\ItemUnit;
use App\Enums\Status;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\Items\Pages\CreateItem;
use App\Filament\Resources\Items\Pages\EditItem;
use App\Filament\Resources\Items\Pages\ListItems;
use App\Models\ProductItem;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
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
            Section::make('Product Item')->schema([
                self::companySelect(),
                Select::make('category_id')->relationship('category', 'name')->searchable()->preload(),
                Select::make('brand_id')->relationship('brand', 'name')->searchable()->preload(),
                TextInput::make('item_code')->maxLength(255),
                TextInput::make('barcode')->maxLength(255),
                TextInput::make('name')->required()->maxLength(255),
                Select::make('unit')->options(ItemUnit::class)->default(ItemUnit::Pieces)->required(),
                self::moneyInput('purchase_price'),
                self::moneyInput('sale_price'),
                TextInput::make('vat_rate')->numeric()->default(20)->step('0.01'),
                Toggle::make('stock_enabled')->default(true),
                TextInput::make('opening_stock')->numeric()->default(0)->step('0.001'),
                Select::make('status')->options(Status::class)->default(Status::Active)->required(),
                Textarea::make('description')->columnSpanFull(),
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
                TextColumn::make('item_code')->searchable()->sortable(),
                TextColumn::make('barcode')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('unit')->sortable(),
                TextColumn::make('sale_price')->money('GBP')->sortable(),
                TextColumn::make('vat_rate')->suffix('%')->sortable(),
                IconColumn::make('stock_enabled')->boolean(),
                TextColumn::make('status')->badge()->sortable(),
            ])
            ->filters([self::statusFilter(Status::class)])
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
        $record = ProductItem::create($data);

        self::syncProductImages($record, $imagePaths);

        return $record;
    }

    public static function updateRecordWithProductImages(ProductItem $record, array $data): void
    {
        $imagePaths = self::pullProductImagePaths($data);

        $record->update($data);
        self::syncProductImages($record, $imagePaths);
    }

    private static function pullProductImagePaths(array &$data): array
    {
        $imagePaths = Arr::wrap($data['product_images'] ?? []);

        unset($data['product_images']);

        return array_values(array_filter($imagePaths, is_string(...)));
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
