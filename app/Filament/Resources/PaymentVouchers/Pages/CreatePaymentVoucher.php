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

    protected ?bool $hasDatabaseTransactions = true;

    private bool $postAfterCreate = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $calculationData = $data;
        $calculationData['allocations'] = $this->data['allocations'] ?? [];
        $calculationData = PaymentVoucherResource::calculateTotalsFromData($calculationData, true);
        $this->data['allocations'] = $calculationData['allocations'];
        unset($calculationData['allocations']);
        $data = [...$data, ...$calculationData];
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
