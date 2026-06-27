<?php

declare(strict_types=1);

namespace App\Filament\Resources\CashBankReports\Pages;

use App\Filament\Resources\CashBankReports\CashBankReportResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListCashBankReports extends ListRecords
{
    protected static string $resource = CashBankReportResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Cash & Bank Reports'),
            'cash_book' => Tab::make('Cash Book')
                ->query(fn (Builder $query): Builder => CashBankReportResource::cashBookQuery($query)),
            'bank_book' => Tab::make('Bank Book')
                ->query(fn (Builder $query): Builder => CashBankReportResource::bankBookQuery($query)),
            'petty_cash_book' => Tab::make('Petty Cash Book')
                ->query(fn (Builder $query): Builder => CashBankReportResource::pettyCashBookQuery($query)),
        ];
    }
}
