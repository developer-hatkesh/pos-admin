<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockReports\Pages;

use App\Filament\Resources\StockReports\StockReportResource;
use App\Support\Inventory\StockReportSql;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListStockReports extends ListRecords
{
    protected static string $resource = StockReportResource::class;

    public function getTabs(): array
    {
        $currentStockSql = StockReportSql::currentStockSql();

        return [
            'summary' => Tab::make('Stock Summary'),
            'opening_stock' => Tab::make('Opening Stock')
                ->query(fn (Builder $query): Builder => $query->where('opening_stock', '>', 0)),
            'closing_stock' => Tab::make('Closing Stock')
                ->query(fn (Builder $query): Builder => $query->whereRaw("{$currentStockSql} <> 0")),
            'valuation' => Tab::make('Stock Valuation')
                ->query(fn (Builder $query): Builder => $query->whereRaw("({$currentStockSql} * COALESCE(product_items.purchase_price, 0)) <> 0")),
            'low_stock' => Tab::make('Low Stock')
                ->query(fn (Builder $query): Builder => $query
                    ->whereNotNull('stock_alert_qty')
                    ->whereRaw("{$currentStockSql} <= stock_alert_qty")),
            'negative_stock' => Tab::make('Negative Stock')
                ->query(fn (Builder $query): Builder => $query->whereRaw("{$currentStockSql} < 0")),
        ];
    }
}
