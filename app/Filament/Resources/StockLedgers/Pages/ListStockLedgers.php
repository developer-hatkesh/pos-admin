<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockLedgers\Pages;

use App\Enums\StockMovementType;
use App\Filament\Resources\StockLedgers\StockLedgerResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListStockLedgers extends ListRecords
{
    protected static string $resource = StockLedgerResource::class;

    public function getTabs(): array
    {
        return [
            'ledger' => Tab::make('Stock Ledger'),
            'item_movement' => Tab::make('Item Movement'),
            'inward' => Tab::make('Inward')
                ->query(fn (Builder $query): Builder => $query->whereIn('type', StockLedgerResource::inwardTypes())),
            'outward' => Tab::make('Outward')
                ->query(fn (Builder $query): Builder => $query->whereIn('type', StockLedgerResource::outwardTypes())),
            'purchases' => Tab::make('Purchase / Sales Return')
                ->query(fn (Builder $query): Builder => $query->whereIn('type', [
                    StockMovementType::Purchase->value,
                    StockMovementType::SalesReturn->value,
                ])),
            'sales' => Tab::make('Sales / Purchase Return')
                ->query(fn (Builder $query): Builder => $query->whereIn('type', [
                    StockMovementType::Sale->value,
                    StockMovementType::PurchaseReturn->value,
                ])),
        ];
    }
}
