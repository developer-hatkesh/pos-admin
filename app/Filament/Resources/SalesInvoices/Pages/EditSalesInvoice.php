<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesInvoices\Pages;

use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use App\Services\Accounting\SalesPostingService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditSalesInvoice extends EditRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    private array $attachmentPaths = [];

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->attachmentPaths = SalesInvoiceResource::pullAttachmentPaths($data);

        if (! array_key_exists('items', $data)) {
            return $data;
        }

        return SalesInvoiceResource::calculateTotalsFromData($data);
    }

    protected function afterSave(): void
    {
        $this->record->load('items');

        app(SalesPostingService::class)->recalculate($this->record);

        SalesInvoiceResource::syncAttachment($this->record, $this->attachmentPaths, $this->record::ATTACHMENTS_COLLECTION);
    }

    protected function getRedirectUrl(): string
    {
        return SalesInvoiceResource::getUrl('index');
    }
}
