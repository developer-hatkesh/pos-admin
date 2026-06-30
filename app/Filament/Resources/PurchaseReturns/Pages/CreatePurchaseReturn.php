<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseReturns\Pages;

use App\Enums\PurchaseReturnStatus;
use App\Filament\Resources\PurchaseReturns\PurchaseReturnResource;
use App\Models\PurchaseInvoice;
use App\Services\Accounting\PurchaseReturnPostingService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreatePurchaseReturn extends CreateRecord
{
    protected static string $resource = PurchaseReturnResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    private PurchaseReturnStatus $requestedStatus = PurchaseReturnStatus::Posted;

    protected function fillForm(): void
    {
        $invoiceId = request()->integer('purchase_invoice_id');

        if ($invoiceId < 1) {
            parent::fillForm();

            return;
        }

        $invoice = PurchaseInvoice::query()
            ->with(['items.productItem'])
            ->find($invoiceId);

        if (! $invoice) {
            parent::fillForm();

            return;
        }

        $this->form->fill(PurchaseReturnResource::dataFromInvoice($invoice));
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->requestedStatus = PurchaseReturnStatus::tryFrom((string) ($data['status'] ?? '')) ?? PurchaseReturnStatus::Posted;
        $data = PurchaseReturnResource::prepareDataForSave($data);

        if ($this->requestedStatus === PurchaseReturnStatus::Posted) {
            $data['status'] = PurchaseReturnStatus::Draft->value;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->requestedStatus !== PurchaseReturnStatus::Posted || $this->record->status !== PurchaseReturnStatus::Draft) {
            return;
        }

        app(PurchaseReturnPostingService::class)->post($this->record);

        Notification::make()
            ->title('Purchase return posted and stock reduced')
            ->success()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return PurchaseReturnResource::getUrl('index');
    }
}
