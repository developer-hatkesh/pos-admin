<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesInvoices\Pages;

use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use App\Services\Accounting\SalesPostingService;
use App\Support\CurrentCompany;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateSalesInvoice extends CreateRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = SalesInvoiceResource::calculateTotalsFromData($data);
        $data['invoice_no'] = SalesInvoiceResource::nextInvoiceNumber(
            $data['company_id'] ?? app(CurrentCompany::class)->id(),
            $data['invoice_date'] ?? now(),
        );

        return $data;
    }

    protected function afterCreate(): void
    {
        app(SalesPostingService::class)->post($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return SalesInvoiceResource::getUrl('index');
    }
}
