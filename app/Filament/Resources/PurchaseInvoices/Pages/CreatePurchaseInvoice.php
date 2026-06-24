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

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = PurchaseInvoiceResource::calculateTotalsFromData($data);
        $data['invoice_no'] = PurchaseInvoiceResource::nextInvoiceNumber(
            $data['company_id'] ?? app(CurrentCompany::class)->id(),
            $data['invoice_date'] ?? now(),
        );
        $data['status'] = InvoiceStatus::Draft->value;

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->load('items');

        app(PurchasePostingService::class)->post($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return PurchaseInvoiceResource::getUrl('index');
    }
}
