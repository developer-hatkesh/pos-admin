<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesReturns\Pages;

use App\Filament\Resources\SalesReturns\SalesReturnResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditSalesReturn extends EditRecord
{
    protected static string $resource = SalesReturnResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    private array $selectedSalesInvoiceIds = [];

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->selectedSalesInvoiceIds = SalesReturnResource::selectedSalesInvoiceIdsFromData($data);

        return SalesReturnResource::prepareDataForSave($data);
    }

    protected function afterSave(): void
    {
        if ($this->selectedSalesInvoiceIds !== []) {
            $this->record->salesInvoices()->sync($this->selectedSalesInvoiceIds);
        }
    }
}
