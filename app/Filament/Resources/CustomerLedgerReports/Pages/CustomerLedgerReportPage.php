<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomerLedgerReports\Pages;

use App\Filament\Resources\CustomerLedgerReports\CustomerLedgerReportResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class CustomerLedgerReportPage extends ListRecords
{
    protected static string $resource = CustomerLedgerReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Export Excel')
                ->icon(Heroicon::ArrowDownTray)
                ->url(route('reports.customer-ledger.export', ['format' => 'csv'])),
            Action::make('exportPdf')
                ->label('Export PDF')
                ->icon(Heroicon::DocumentText)
                ->url(route('reports.customer-ledger.print', ['pdf' => 1]))
                ->openUrlInNewTab(),
            Action::make('print')
                ->label('Print')
                ->icon(Heroicon::Printer)
                ->url(route('reports.customer-ledger.print'))
                ->openUrlInNewTab(),
        ];
    }
}
