<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomerLedgerReports\Pages;

use App\Filament\Resources\CustomerLedgerReports\CustomerLedgerReportResource;
use App\Models\Customer;
use App\Services\Reports\CustomerLedgerReportService;
use App\Services\Accounting\CustomerCreditReconciliationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class CustomerLedgerDetailPage extends ViewRecord
{
    protected static string $resource = CustomerLedgerReportResource::class;

    protected string $view = 'reports.ledger.filament-detail';

    public function getViewData(): array
    {
        /** @var Customer $customer */
        $customer = $this->record->loadMissing(['company', 'ledger.parent']);
        $from = request('from');
        $to = request('to');

        return [
            'party' => $customer,
            'partyType' => 'Customer',
            'title' => 'Customer Ledger Report',
            ...app(CustomerLedgerReportService::class)->detail($customer, $from, $to),
            'fromDate' => $from,
            'toDate' => $to,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('allocateCredit')
                ->label('Auto Allocate Credit')
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->requiresConfirmation()
                ->modalHeading('Allocate available customer credit?')
                ->modalDescription('Available receipts and credit notes will be allocated to the oldest outstanding invoices. No new accounting journal will be created.')
                ->action(function (): void {
                    $result = app(CustomerCreditReconciliationService::class)->reconcile($this->record);

                    Notification::make()
                        ->title($result['total_allocated'] > 0 ? 'Customer credit allocated' : 'Nothing to allocate')
                        ->body(app_money($result['total_allocated']).' allocated across '.$result['invoice_count'].' invoice(s).')
                        ->success()
                        ->send();
                }),
            Action::make('exportExcel')->label('Export Excel')->icon(Heroicon::ArrowDownTray)
                ->url(fn (): string => route('reports.customer-ledger.detail.export', ['customer' => $this->record, 'format' => 'csv'])),
            Action::make('exportPdf')->label('Export PDF')->icon(Heroicon::DocumentText)
                ->url(fn (): string => route('reports.customer-ledger.detail.print', ['customer' => $this->record, 'pdf' => 1]))->openUrlInNewTab(),
            Action::make('print')->label('Print')->icon(Heroicon::Printer)
                ->url(fn (): string => route('reports.customer-ledger.detail.print', ['customer' => $this->record]))->openUrlInNewTab(),
        ];
    }
}
