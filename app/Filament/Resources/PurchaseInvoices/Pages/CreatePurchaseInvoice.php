<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseInvoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Services\Accounting\PurchasePostingService;
use App\Support\CurrentCompany;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreatePurchaseInvoice extends CreateRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected ?bool $hasDatabaseTransactions = true;

    private array $attachmentPaths = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->attachmentPaths = PurchaseInvoiceResource::pullAttachmentPaths($data);
        $calculationData = $data;
        $calculationData['items'] = $this->data['items'] ?? [];
        $isBlankInvoice = $calculationData['items'] === [];

        if (! $isBlankInvoice) {
            PurchaseInvoiceResource::validateItemsForSave($calculationData);
        } else {
            $calculationData['discount'] = 0;
            $calculationData['shipping'] = 0;
        }
        $calculationData = PurchaseInvoiceResource::calculateTotalsFromData($calculationData);
        $this->data['items'] = $calculationData['items'];
        unset($calculationData['items']);
        $data = [...$data, ...$calculationData];
        $data['voucher_no'] = PurchaseInvoiceResource::nextInvoiceNumber(
            $data['company_id'] ?? app(CurrentCompany::class)->id(),
            $data['invoice_date'] ?? now(),
        );
        $data['invoice_no'] = $data['voucher_no'];
        $data['status'] = InvoiceStatus::Draft->value;

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->load('items');

        if ($this->record->items->isNotEmpty()) {
            app(PurchasePostingService::class)->post($this->record);
        }

        PurchaseInvoiceResource::syncAttachment($this->record, $this->attachmentPaths, $this->record::ATTACHMENTS_COLLECTION);
    }

    protected function getRedirectUrl(): string
    {
        return PurchaseInvoiceResource::getUrl('index');
    }
}
