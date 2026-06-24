<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesReturns\Pages;

use App\Enums\SalesReturnStatus;
use App\Filament\Resources\SalesReturns\SalesReturnResource;
use App\Models\SalesInvoice;
use App\Services\Accounting\SalesReturnPostingService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateSalesReturn extends CreateRecord
{
    protected static string $resource = SalesReturnResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    private SalesReturnStatus $requestedStatus = SalesReturnStatus::Posted;

    private array $selectedSalesInvoiceIds = [];

    protected function fillForm(): void
    {
        $invoiceId = request()->integer('sales_invoice_id');

        if ($invoiceId < 1) {
            parent::fillForm();

            return;
        }

        $invoice = SalesInvoice::query()
            ->with(['items'])
            ->find($invoiceId);

        if (! $invoice) {
            parent::fillForm();

            return;
        }

        $this->form->fill(SalesReturnResource::dataFromInvoice($invoice));
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->requestedStatus = SalesReturnStatus::tryFrom((string) ($data['status'] ?? '')) ?? SalesReturnStatus::Posted;
        $this->selectedSalesInvoiceIds = SalesReturnResource::selectedSalesInvoiceIdsFromData($data);
        $data = SalesReturnResource::prepareDataForSave($data);

        if ($this->requestedStatus === SalesReturnStatus::Posted) {
            $data['status'] = SalesReturnStatus::Draft->value;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->selectedSalesInvoiceIds !== []) {
            $this->record->salesInvoices()->sync($this->selectedSalesInvoiceIds);
        }

        if ($this->requestedStatus !== SalesReturnStatus::Posted || $this->record->status !== SalesReturnStatus::Draft) {
            return;
        }

        app(SalesReturnPostingService::class)->post($this->record);

        Notification::make()
            ->title('Sales return posted and stock restored')
            ->success()
            ->send();
    }
}
