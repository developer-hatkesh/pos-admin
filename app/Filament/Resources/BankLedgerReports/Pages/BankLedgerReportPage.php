<?php

declare(strict_types=1);

namespace App\Filament\Resources\BankLedgerReports\Pages;

use App\Filament\Resources\BankLedgerReports\BankLedgerReportResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class BankLedgerReportPage extends ListRecords
{
    protected static string $resource = BankLedgerReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Export Excel')
                ->icon(Heroicon::ArrowDownTray)
                ->url(route('reports.bank-ledger.export', ['format' => 'csv'])),
            Action::make('exportPdf')
                ->label('Export PDF')
                ->icon(Heroicon::DocumentText)
                ->url(route('reports.bank-ledger.print', ['pdf' => 1]))
                ->openUrlInNewTab(),
            Action::make('print')
                ->label('Print')
                ->icon(Heroicon::Printer)
                ->url(route('reports.bank-ledger.print'))
                ->openUrlInNewTab(),
        ];
    }
}
