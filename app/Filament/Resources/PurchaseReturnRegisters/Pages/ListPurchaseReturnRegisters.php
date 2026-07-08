<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseReturnRegisters\Pages;

use App\Filament\Resources\PurchaseReturnRegisters\PurchaseReturnRegisterResource;
use App\Filament\Resources\Reports\Concerns\HasPermanentRegisterFilters;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class ListPurchaseReturnRegisters extends ListRecords
{
    use HasPermanentRegisterFilters;

    protected static string $resource = PurchaseReturnRegisterResource::class;

    protected string $registerPartyType = 'supplier';

    protected string $registerPartyColumn = 'supplier_id';

    protected string $registerDateColumn = 'return_date';

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $this->applyRegisterFilters($query));
    }

    protected function getTableHeader(): View
    {
        return view('reports.registers.permanent-filters', [
            'searchPlaceholder' => 'Search purchase returns',
        ]);
    }
}
