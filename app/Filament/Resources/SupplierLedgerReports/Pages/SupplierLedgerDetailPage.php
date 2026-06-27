<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupplierLedgerReports\Pages;

use App\Filament\Resources\SupplierLedgerReports\SupplierLedgerReportResource;
use App\Models\Supplier;
use App\Services\Reports\SupplierLedgerReportService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class SupplierLedgerDetailPage extends ViewRecord
{
    protected static string $resource = SupplierLedgerReportResource::class;

    protected string $view = 'reports.ledger.filament-detail';

    public function getViewData(): array
    {
        /** @var Supplier $supplier */
        $supplier = $this->record->loadMissing(['company', 'ledger.parent']);
        $from = request('from');
        $to = request('to');

        return [
            'party' => $supplier,
            'partyType' => 'Supplier',
            'title' => 'Supplier Ledger Report',
            ...app(SupplierLedgerReportService::class)->detail($supplier, $from, $to),
            'fromDate' => $from,
            'toDate' => $to,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')->label('Export Excel')->icon(Heroicon::ArrowDownTray)
                ->url(fn (): string => route('reports.supplier-ledger.detail.export', ['supplier' => $this->record, 'format' => 'csv'])),
            Action::make('exportPdf')->label('Export PDF')->icon(Heroicon::DocumentText)
                ->url(fn (): string => route('reports.supplier-ledger.detail.print', ['supplier' => $this->record, 'pdf' => 1]))->openUrlInNewTab(),
            Action::make('print')->label('Print')->icon(Heroicon::Printer)
                ->url(fn (): string => route('reports.supplier-ledger.detail.print', ['supplier' => $this->record]))->openUrlInNewTab(),
        ];
    }
}
