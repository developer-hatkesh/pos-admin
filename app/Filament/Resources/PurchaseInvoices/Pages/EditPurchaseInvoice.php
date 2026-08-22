<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseInvoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Services\Accounting\PurchasePostingService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditPurchaseInvoice extends EditRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    private array $attachmentPaths = [];

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->attachmentPaths = PurchaseInvoiceResource::pullAttachmentPaths($data);

        if (! array_key_exists('items', $data)) {
            return $data;
        }

        if ($data['items'] === [] && $this->record->status === InvoiceStatus::Draft) {
            $data['status'] = InvoiceStatus::Draft->value;
            $data['discount'] = 0;
            $data['shipping'] = 0;

            return PurchaseInvoiceResource::calculateTotalsFromData($data);
        }

        PurchaseInvoiceResource::validateItemsForSave($data);

        return PurchaseInvoiceResource::calculateTotalsFromData($data);
    }

    protected function afterSave(): void
    {
        $this->record->load('items');

        app(PurchasePostingService::class)->recalculate($this->record);

        PurchaseInvoiceResource::syncAttachment($this->record, $this->attachmentPaths, $this->record::ATTACHMENTS_COLLECTION);
    }

    protected function getRedirectUrl(): string
    {
        return PurchaseInvoiceResource::getUrl('index');
    }
}
