<?php

declare(strict_types=1);

namespace App\Filament\Resources\ItemSalesReports;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\ItemSalesReports\Pages\ListItemSalesReports;
use App\Models\ProductItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ItemSalesReportResource extends Resource
{
    protected static ?string $model = ProductItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?string $navigationParentItem = 'Sales Reports';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Item Sales Report';

    protected static ?string $modelLabel = 'Item Sales Report';

    protected static ?string $pluralModelLabel = 'Item Sales Report';

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['category', 'brand'])
            ->select('product_items.*')
            ->selectRaw('0 as sold_qty')
            ->selectRaw('0 as sold_total');
    }

    public static function applySalesReportSelects(Builder $query, string $startDate, string $endDate): Builder
    {
        $invoiceScope = fn (Builder $query): Builder => $query
            ->whereDate('invoice_date', '>=', $startDate)
            ->whereDate('invoice_date', '<=', $endDate)
            ->whereIn('status', self::activeSalesStatuses());

        $lineScope = fn (Builder $query): Builder => $query->whereHas('salesInvoice', $invoiceScope);

        return $query
            ->select('product_items.*')
            ->withSum(['salesInvoiceItems as sold_qty' => $lineScope], 'qty')
            ->withSum(['salesInvoiceItems as sold_total' => $lineScope], 'line_total')
            ->whereHas('salesInvoiceItems', $lineScope);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Item')
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
                TextColumn::make('brand.name')
                    ->label('Brand')
                    ->placeholder('No brand')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sold_qty')
                    ->label('Qty')
                    ->numeric(decimalPlaces: 3)
                    ->sortable(),
                TextColumn::make('sold_total')
                    ->label('Line Total')
                    ->formatStateUsing(fn (mixed $state): string => app_money((float) $state))
                    ->sortable(),
            ])
            ->defaultSort('sold_total', 'desc')
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => ListItemSalesReports::route('/')];
    }

    private static function activeSalesStatuses(): array
    {
        return [
            InvoiceStatus::Posted->value,
            InvoiceStatus::Paid->value,
            InvoiceStatus::Partial->value,
        ];
    }
}
