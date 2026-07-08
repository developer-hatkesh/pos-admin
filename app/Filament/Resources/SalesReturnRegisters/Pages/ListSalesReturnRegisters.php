<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesReturnRegisters\Pages;

use App\Filament\Resources\Reports\Concerns\HasPermanentRegisterFilters;
use App\Filament\Resources\SalesReturnRegisters\SalesReturnRegisterResource;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\Pages\ListRecords;

class ListSalesReturnRegisters extends ListRecords
{
    use HasPermanentRegisterFilters;

    protected static string $resource = SalesReturnRegisterResource::class;

    protected string $registerPartyType = 'customer';

    protected string $registerPartyColumn = 'customer_id';

    protected string $registerDateColumn = 'return_date';

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $this->applyRegisterFilters($query));
    }

    protected function getTableHeader(): View
    {
        return view('reports.registers.permanent-filters', [
            'searchPlaceholder' => 'Search sales returns',
        ]);
    }
}
