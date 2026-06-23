<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentVouchers\Pages;

use App\Enums\VoucherStatus;
use App\Filament\Resources\PaymentVouchers\PaymentVoucherResource;
use App\Services\Accounting\VoucherPostingService;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentVoucher extends CreateRecord
{
    protected static string $resource = PaymentVoucherResource::class;

    private bool $postAfterCreate = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = PaymentVoucherResource::calculateTotalsFromData($data);
        $this->postAfterCreate = ($data['status'] ?? null) === VoucherStatus::Posted->value;

        if ($this->postAfterCreate) {
            $data['status'] = VoucherStatus::Draft->value;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        if (! $this->postAfterCreate) {
            return;
        }

        app(VoucherPostingService::class)->post($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return PaymentVoucherResource::getUrl('index');
    }
}
