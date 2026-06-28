<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomerLedgerReports\Pages;

use App\Filament\Resources\CustomerLedgerReports\CustomerLedgerReportResource;
use App\Filament\Resources\Reports\Concerns\HasPermanentLedgerReportFilters;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\View\View;

class CustomerLedgerReportPage extends ListRecords
{
    use HasPermanentLedgerReportFilters;

    protected static string $resource = CustomerLedgerReportResource::class;

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => CustomerLedgerReportResource::applyPermanentFilters($query, $this));
    }

    protected function getTableHeader(): View
    {
        return view('reports.ledger.permanent-filters', [
            'searchPlaceholder' => 'Search customer',
            'exportRoute' => 'reports.customer-ledger.export',
            'printRoute' => 'reports.customer-ledger.print',
        ]);
    }
}
