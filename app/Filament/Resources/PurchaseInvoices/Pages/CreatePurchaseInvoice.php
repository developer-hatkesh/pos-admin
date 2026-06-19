<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseInvoices\Pages;

use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreatePurchaseInvoice extends CreateRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return PurchaseInvoiceResource::calculateTotalsFromData($data);
    }
}
