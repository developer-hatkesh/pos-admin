<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupplierLedgerReports\Pages;

use App\Filament\Resources\SupplierLedgerReports\SupplierLedgerReportResource;
use App\Filament\Resources\Reports\Concerns\HasPermanentLedgerReportFilters;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\View\View;

class SupplierLedgerReportPage extends ListRecords
{
    use HasPermanentLedgerReportFilters;

    protected static string $resource = SupplierLedgerReportResource::class;

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => SupplierLedgerReportResource::applyPermanentFilters($query, $this));
    }

    protected function getTableHeader(): View
    {
        return view('reports.ledger.permanent-filters', [
            'searchPlaceholder' => 'Search supplier',
            'exportRoute' => 'reports.supplier-ledger.export',
            'printRoute' => 'reports.supplier-ledger.print',
        ]);
    }
}
