<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockLedgers;

use App\Enums\StockMovementType;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\StockLedgers\Pages\ListStockLedgers;
use App\Models\StockMovement;
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

class StockLedgerResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = StockMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Stock Ledger';

    protected static ?string $modelLabel = 'Stock Ledger Entry';

    protected static ?string $pluralModelLabel = 'Stock Ledger';

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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('movement_date')->label('Date')->date()->sortable(),
                TextColumn::make('productItem.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->description(fn (StockMovement $record): string => collect([
                        $record->productItem?->sku ? 'SKU: '.$record->productItem->sku : null,
                        $record->productItem?->item_code ? 'Item: '.$record->productItem->item_code : null,
                    ])->filter()->implode(' | ')),
                TextColumn::make('type')
                    ->label('Source')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (StockMovementType|string|null $state): string => $state instanceof StockMovementType ? $state->label() : (StockMovementType::tryFrom((string) $state)?->label() ?? '-'))
                    ->color(fn (StockMovement $record): string => $record->type->increasesStock() ? 'success' : 'danger'),
                TextColumn::make('inward_qty')
                    ->label('Inward')
                    ->state(fn (StockMovement $record): string => $record->type->increasesStock() ? (string) $record->quantity : '0')
                    ->numeric(decimalPlaces: 3),
                TextColumn::make('outward_qty')
                    ->label('Outward')
                    ->state(fn (StockMovement $record): string => $record->type->increasesStock() ? '0' : (string) $record->quantity)
                    ->numeric(decimalPlaces: 3),
                TextColumn::make('rate')
                    ->label('Rate')
                    ->formatStateUsing(fn (mixed $state): string => app_money($state))
                    ->sortable(),
                TextColumn::make('value')
                    ->label('Value')
                    ->state(fn (StockMovement $record): float => (float) $record->quantity * (float) $record->rate)
                    ->formatStateUsing(fn (mixed $state): string => app_money($state)),
                TextColumn::make('reference_type')
                    ->label('Reference')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '-')
                    ->description(fn (StockMovement $record): string => $record->reference_id ? '#'.$record->reference_id : ''),
            ])
            ->filters([
                SelectFilter::make('product_item_id')
                    ->label('Product')
                    ->relationship('productItem', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('type')->options(StockMovementType::options()),
                self::dateRangeFilter('movement_date'),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('productItem'))
            ->defaultSort('movement_date', 'desc')
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function inwardTypes(): array
    {
        return StockReportSql::inwardTypes();
    }

    public static function outwardTypes(): array
    {
        return StockReportSql::outwardTypes();
    }

    public static function getPages(): array
    {
        return ['index' => ListStockLedgers::route('/')];
    }
}
