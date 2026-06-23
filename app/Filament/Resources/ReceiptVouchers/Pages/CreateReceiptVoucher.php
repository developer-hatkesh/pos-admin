<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReceiptVouchers\Pages;

use App\Enums\VoucherStatus;
use App\Filament\Resources\ReceiptVouchers\ReceiptVoucherResource;
use App\Services\Accounting\VoucherPostingService;
use Filament\Resources\Pages\CreateRecord;

class CreateReceiptVoucher extends CreateRecord
{
    protected static string $resource = ReceiptVoucherResource::class;

    private bool $postAfterCreate = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = ReceiptVoucherResource::calculateTotalsFromData($data);
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
        return ReceiptVoucherResource::getUrl('index');
    }
}
