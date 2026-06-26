<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockReports;

use App\Enums\ProductType;
use App\Filament\Resources\StockReports\Pages\ListStockReports;
use App\Models\ProductItem;
use App\Support\Inventory\StockReportSql;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class StockReportResource extends Resource
{
    protected static ?string $model = ProductItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Stock Reports';

    protected static ?string $modelLabel = 'Stock Report';

    protected static ?string $pluralModelLabel = 'Stock Reports';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $currentStockSql = StockReportSql::currentStockSql();
        $inwardQtySql = StockReportSql::movementQuantitySql(true);
        $outwardQtySql = StockReportSql::movementQuantitySql(false);

        return parent::getEloquentQuery()
            ->where('stock_enabled', true)
            ->where('product_type', '!=', ProductType::Service->value)
            ->select('product_items.*')
            ->selectRaw("{$inwardQtySql} as inward_qty")
            ->selectRaw("{$outwardQtySql} as outward_qty")
            ->selectRaw("{$currentStockSql} as closing_qty")
            ->selectRaw("({$currentStockSql} * COALESCE(product_items.purchase_price, 0)) as stock_value");
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->description(fn (ProductItem $record): string => collect([
                        $record->sku ? 'SKU: '.$record->sku : null,
                        $record->item_code ? 'Item: '.$record->item_code : null,
                    ])->filter()->implode(' | ')),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->placeholder('No category')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('opening_stock')
                    ->label('Opening')
                    ->numeric(decimalPlaces: 3)
                    ->sortable(),
                TextColumn::make('inward_qty')
                    ->label('Inward')
                    ->numeric(decimalPlaces: 3)
                    ->sortable(),
                TextColumn::make('outward_qty')
                    ->label('Outward')
                    ->numeric(decimalPlaces: 3)
                    ->sortable(),
                TextColumn::make('closing_qty')
                    ->label('Closing')
                    ->numeric(decimalPlaces: 3)
                    ->sortable()
                    ->color(fn (mixed $state): string => ((float) $state < 0) ? 'danger' : 'success'),
                TextColumn::make('stock_alert_qty')
                    ->label('Alert')
                    ->numeric(decimalPlaces: 3)
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('purchase_price')
                    ->label('Cost')
                    ->formatStateUsing(fn (mixed $state): string => app_money($state))
                    ->sortable(),
                TextColumn::make('stock_value')
                    ->label('Valuation')
                    ->formatStateUsing(fn (mixed $state): string => app_money($state))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('name')
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => ListStockReports::route('/')];
    }
}
