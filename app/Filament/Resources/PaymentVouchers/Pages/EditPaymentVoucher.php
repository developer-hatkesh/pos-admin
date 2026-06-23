<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentVouchers\Pages;

use App\Enums\VoucherStatus;
use App\Filament\Resources\PaymentVouchers\PaymentVoucherResource;
use App\Services\Accounting\VoucherPostingService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPaymentVoucher extends EditRecord
{
    protected static string $resource = PaymentVoucherResource::class;

    private bool $postAfterSave = false;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = PaymentVoucherResource::calculateTotalsFromData($data);

        if ($this->record->status === VoucherStatus::Posted || $this->record->bank_transaction_id !== null) {
            $data['status'] = $this->record->status instanceof VoucherStatus
                ? $this->record->status->value
                : (string) $this->record->status;
            $this->postAfterSave = false;

            return $data;
        }

        $this->postAfterSave = ($data['status'] ?? null) === VoucherStatus::Posted->value
            && $this->record->status !== VoucherStatus::Posted
            && $this->record->bank_transaction_id === null;

        if ($this->postAfterSave) {
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

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return PaymentVoucherResource::getUrl('index');
    }
}
