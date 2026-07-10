<?php

declare(strict_types=1);

namespace App\Filament\Resources\Estimates\Pages;

use App\Filament\Resources\Estimates\EstimateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditEstimate extends EditRecord
{
    protected static string $resource = EstimateResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [
            EstimateResource::convertToInvoiceAction(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! array_key_exists('items', $data)) {
            return $data;
        }

        return EstimateResource::calculateTotalsFromData($data);
    }

    protected function afterSave(): void
    {
        EstimateResource::recalculateStoredTotals($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return EstimateResource::getUrl('index');
    }
}
