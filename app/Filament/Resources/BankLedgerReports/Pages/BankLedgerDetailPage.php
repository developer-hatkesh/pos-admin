<?php

declare(strict_types=1);

namespace App\Filament\Resources\BankLedgerReports\Pages;

use App\Filament\Resources\BankLedgerReports\BankLedgerReportResource;
use App\Models\BankAccount;
use App\Services\Reports\BankLedgerReportService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class BankLedgerDetailPage extends ViewRecord
{
    protected static string $resource = BankLedgerReportResource::class;

    protected string $view = 'reports.ledger.filament-detail';

    public function getViewData(): array
    {
        /** @var BankAccount $bankAccount */
        $bankAccount = $this->record->loadMissing(['company', 'ledger.parent']);
        $from = request('from');
        $to = request('to');

        return [
            'party' => $bankAccount,
            'partyType' => 'Bank',
            'title' => 'Bank Ledger Report',
            ...app(BankLedgerReportService::class)->detail($bankAccount, $from, $to),
            'fromDate' => $from,
            'toDate' => $to,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')->label('Export Excel')->icon(Heroicon::ArrowDownTray)
                ->url(fn (): string => route('reports.bank-ledger.detail.export', ['bankAccount' => $this->record, 'format' => 'csv'])),
            Action::make('exportPdf')->label('Export PDF')->icon(Heroicon::DocumentText)
                ->url(fn (): string => route('reports.bank-ledger.detail.print', ['bankAccount' => $this->record, 'pdf' => 1]))->openUrlInNewTab(),
            Action::make('print')->label('Print')->icon(Heroicon::Printer)
                ->url(fn (): string => route('reports.bank-ledger.detail.print', ['bankAccount' => $this->record]))->openUrlInNewTab(),
        ];
    }
}
