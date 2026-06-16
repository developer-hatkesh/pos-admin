<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesInvoices\Pages;

use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSalesInvoice extends EditRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return SalesInvoiceResource::calculateTotalsFromData($data);
    }
}
