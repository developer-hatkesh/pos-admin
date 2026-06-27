<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupplierLedgerReports\Pages;

use App\Filament\Resources\SupplierLedgerReports\SupplierLedgerReportResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class SupplierLedgerReportPage extends ListRecords
{
    protected static string $resource = SupplierLedgerReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Export Excel')
                ->icon(Heroicon::ArrowDownTray)
                ->url(route('reports.supplier-ledger.export', ['format' => 'csv'])),
            Action::make('exportPdf')
                ->label('Export PDF')
                ->icon(Heroicon::DocumentText)
                ->url(route('reports.supplier-ledger.print', ['pdf' => 1]))
                ->openUrlInNewTab(),
            Action::make('print')
                ->label('Print')
                ->icon(Heroicon::Printer)
                ->url(route('reports.supplier-ledger.print'))
                ->openUrlInNewTab(),
        ];
    }
}
