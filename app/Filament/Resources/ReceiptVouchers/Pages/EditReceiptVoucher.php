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

    protected ?bool $hasDatabaseTransactions = true;

    private bool $postAfterSave = false;

    /** @var array<int, int> */
    private array $previousSalesInvoiceIds = [];

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->databaseTransaction()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->previousSalesInvoiceIds = $this->record->allocations()
            ->whereNotNull('sales_invoice_id')
            ->pluck('sales_invoice_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
        $data = ReceiptVoucherResource::calculateTotalsFromData($data);
        $this->postAfterSave = ($data['status'] ?? null) === VoucherStatus::Posted->value
            && $this->record->status !== VoucherStatus::Posted
            && $this->record->bank_transaction_id === null;

        ReceiptVoucherResource::validatePostableData($data, $this->record);

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

        $currentSalesInvoiceIds = $this->record->allocations()
            ->whereNotNull('sales_invoice_id')
            ->pluck('sales_invoice_id');

        app(VoucherPostingService::class)->syncSalesInvoiceStatuses([
            ...$this->previousSalesInvoiceIds,
            ...$currentSalesInvoiceIds->all(),
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return ReceiptVoucherResource::getUrl('index');
    }
}
