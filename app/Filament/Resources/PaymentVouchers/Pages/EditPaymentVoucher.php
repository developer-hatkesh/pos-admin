<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentVouchers\Pages;

use App\Filament\Resources\PaymentVouchers\PaymentVoucherResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPaymentVoucher extends EditRecord
{
    protected static string $resource = PaymentVoucherResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return PaymentVoucherResource::calculateTotalsFromData($data);
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
