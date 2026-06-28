<?php

declare(strict_types=1);

namespace App\Filament\Resources\BankLedgerReports\Pages;

use App\Filament\Resources\BankLedgerReports\BankLedgerReportResource;
use App\Filament\Resources\Reports\Concerns\HasPermanentLedgerReportFilters;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\View\View;

class BankLedgerReportPage extends ListRecords
{
    use HasPermanentLedgerReportFilters;

    protected static string $resource = BankLedgerReportResource::class;

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => BankLedgerReportResource::applyPermanentFilters($query, $this));
    }

    protected function getTableHeader(): View
    {
        return view('reports.ledger.permanent-filters', [
            'searchPlaceholder' => 'Search bank account',
            'exportRoute' => 'reports.bank-ledger.export',
            'printRoute' => 'reports.bank-ledger.print',
        ]);
    }
}
