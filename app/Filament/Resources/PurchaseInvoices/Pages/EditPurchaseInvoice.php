<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseInvoices\Pages;

use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Services\Accounting\PurchasePostingService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditPurchaseInvoice extends EditRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! array_key_exists('items', $data)) {
            return $data;
        }

        return PurchaseInvoiceResource::calculateTotalsFromData($data);
    }

    protected function afterSave(): void
    {
        $this->record->load('items');

        app(PurchasePostingService::class)->recalculate($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return PurchaseInvoiceResource::getUrl('index');
    }
}
