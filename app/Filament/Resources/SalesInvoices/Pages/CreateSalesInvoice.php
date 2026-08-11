<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesInvoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use App\Services\Accounting\SalesPostingService;
use App\Support\CurrentCompany;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateSalesInvoice extends CreateRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected ?bool $hasDatabaseTransactions = true;

    private InvoiceStatus $requestedStatus = InvoiceStatus::Posted;

    private array $attachmentPaths = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->attachmentPaths = SalesInvoiceResource::pullAttachmentPaths($data);
        $data = SalesInvoiceResource::calculateTotalsFromData($data);
        $this->requestedStatus = InvoiceStatus::tryFrom((string) ($data['status'] ?? '')) ?? InvoiceStatus::Posted;

        if ($this->requestedStatus === InvoiceStatus::Posted) {
            $data['status'] = InvoiceStatus::Draft->value;
        }

        $data['invoice_no'] = SalesInvoiceResource::nextInvoiceNumber(
            $data['company_id'] ?? app(CurrentCompany::class)->id(),
            $data['invoice_date'] ?? now(),
        );

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->load('items');

        app(SalesPostingService::class)->recalculate($this->record);

        SalesInvoiceResource::syncAttachment($this->record, $this->attachmentPaths, $this->record::ATTACHMENTS_COLLECTION);

        if ($this->requestedStatus !== InvoiceStatus::Posted || $this->record->status !== InvoiceStatus::Draft) {
            return;
        }

        app(SalesPostingService::class)->post($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return SalesInvoiceResource::getUrl('index');
    }
}
