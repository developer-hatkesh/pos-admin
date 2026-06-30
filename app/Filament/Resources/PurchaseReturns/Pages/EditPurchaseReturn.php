<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseReturns\Pages;

use App\Filament\Resources\PurchaseReturns\PurchaseReturnResource;
use App\Services\Accounting\PurchaseReturnPostingService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditPurchaseReturn extends EditRecord
{
    protected static string $resource = PurchaseReturnResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return PurchaseReturnResource::prepareDataForSave($data, $this->record);
    }

    protected function afterSave(): void
    {
        $this->record->load('items');
        app(PurchaseReturnPostingService::class)->recalculate($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return PurchaseReturnResource::getUrl('index');
    }
}
