<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseRegisters\Pages;

use App\Filament\Resources\PurchaseRegisters\PurchaseRegisterResource;
use App\Filament\Resources\Reports\Concerns\HasPermanentRegisterFilters;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class ListPurchaseRegisters extends ListRecords
{
    use HasPermanentRegisterFilters;

    protected static string $resource = PurchaseRegisterResource::class;

    protected string $registerPartyType = 'supplier';

    protected string $registerPartyColumn = 'supplier_id';

    protected string $registerDateColumn = 'invoice_date';

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $this->applyRegisterFilters($query));
    }

    protected function getTableHeader(): View
    {
        return view('reports.registers.permanent-filters', [
            'searchPlaceholder' => 'Search purchase register',
        ]);
    }
}
