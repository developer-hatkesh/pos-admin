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

    protected ?bool $hasDatabaseTransactions = true;

    private bool $postAfterSave = false;

    /** @var array<int, int> */
    private array $previousPurchaseInvoiceIds = [];

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->previousPurchaseInvoiceIds = $this->record->allocations()
            ->whereNotNull('purchase_invoice_id')
            ->pluck('purchase_invoice_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
        $calculationData = $data;
        $calculationData['allocations'] = $this->data['allocations'] ?? [];
        $derivePaymentAmount = $this->record->status !== VoucherStatus::Posted && $this->record->bank_transaction_id === null;
        $calculationData = PaymentVoucherResource::calculateTotalsFromData($calculationData, $derivePaymentAmount);
        $this->data['allocations'] = $calculationData['allocations'];
        unset($calculationData['allocations']);
        $data = [...$data, ...$calculationData];

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
        if ($this->postAfterSave) {
            app(VoucherPostingService::class)->post($this->record);
        }

        app(VoucherPostingService::class)->syncPurchaseInvoiceStatuses([
            ...$this->previousPurchaseInvoiceIds,
            ...$this->record->allocations()->whereNotNull('purchase_invoice_id')->pluck('purchase_invoice_id')->all(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->databaseTransaction()];
    }

    protected function getRedirectUrl(): string
    {
        return PaymentVoucherResource::getUrl('index');
    }
}
