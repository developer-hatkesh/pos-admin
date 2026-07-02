<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReceiptVouchers\Pages;

use App\Enums\VoucherStatus;
use App\Filament\Resources\ReceiptVouchers\ReceiptVoucherResource;
use App\Services\Accounting\VoucherPostingService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditReceiptVoucher extends EditRecord
{
    protected static string $resource = ReceiptVoucherResource::class;

    private bool $postAfterSave = false;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = ReceiptVoucherResource::calculateTotalsFromData($data);
        $this->postAfterSave = ($data['status'] ?? null) === VoucherStatus::Posted->value
            && $this->record->status !== VoucherStatus::Posted
            && $this->record->bank_transaction_id === null;

        if ($this->postAfterSave) {
            ReceiptVoucherResource::validatePostableData($data);
            $data['status'] = VoucherStatus::Draft->value;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if (! $this->postAfterSave) {
            return;
        }

        app(VoucherPostingService::class)->post($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return ReceiptVoucherResource::getUrl('index');
    }
}
