<?php

declare(strict_types=1);

namespace App\Filament\Resources\Items;

use App\Enums\ItemUnit;
use App\Enums\Status;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\Items\Pages\ManageItems;
use App\Models\ProductItem;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
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
                TextInput::make('name')->required()->maxLength(255),
                Select::make('unit')->options(ItemUnit::class)->default(ItemUnit::Pieces)->required(),
                self::moneyInput('purchase_price'),
                self::moneyInput('sale_price'),
                TextInput::make('vat_rate')->numeric()->default(20)->step('0.01'),
                Toggle::make('stock_enabled')->default(true),
                TextInput::make('opening_stock')->numeric()->default(0)->step('0.001'),
                Select::make('status')->options(Status::class)->default(Status::Active)->required(),
                Textarea::make('description')->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category.name')->searchable()->sortable(),
                TextColumn::make('brand.name')->searchable()->sortable(),
                TextColumn::make('item_code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('unit')->sortable(),
                TextColumn::make('sale_price')->money('GBP')->sortable(),
                TextColumn::make('vat_rate')->suffix('%')->sortable(),
                IconColumn::make('stock_enabled')->boolean(),
                TextColumn::make('status')->badge()->sortable(),
            ])
            ->filters([self::statusFilter(Status::class)])
            ->defaultSort('created_at', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array { return ['index' => ManageItems::route('/')]; }
}
