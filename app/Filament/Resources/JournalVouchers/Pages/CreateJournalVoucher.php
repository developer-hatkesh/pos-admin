<?php

declare(strict_types=1);

namespace App\Filament\Resources\JournalVouchers\Pages;

use App\Filament\Resources\JournalVouchers\JournalVoucherResource;
use App\Services\Accounting\JournalVoucherService;
use App\Services\Accounting\CustomerCreditReconciliationService;
use Filament\Resources\Pages\CreateRecord;

class CreateJournalVoucher extends CreateRecord
{
    protected static string $resource = JournalVoucherResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    private array $journalLines = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->journalLines = $data['journal_lines'] ?? [];
        unset($data['journal_lines']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $service = app(JournalVoucherService::class);
        match ($this->record->form_type) {
            'credit_note' => $service->completeCreditNote($this->record),
            'purchase_return' => $service->completePurchaseReturn($this->record),
            'customer_credit_allocation' => $this->completeCustomerCreditAllocation(),
            default => $service->completeManual($this->record, $this->journalLines),
        };
    }

    private function completeCustomerCreditAllocation(): void
    {
        $customer = $this->record->customer;

        if (! $customer || $customer->company_id !== $this->record->company_id) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'data.customer_id' => 'Select a customer for this company.',
            ]);
        }

        $result = app(CustomerCreditReconciliationService::class)->reconcile($customer);

        if ($result['total_allocated'] <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'data.customer_id' => 'This customer has no unallocated credit that can be matched to an outstanding invoice.',
            ]);
        }

        $this->record->update([
            'narration' => 'Customer credit allocation for '.$customer->name.': '.app_money($result['total_allocated']).' allocated across '.$result['invoice_count'].' invoice(s). No accounting journal created.',
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return JournalVoucherResource::getUrl('index');
    }
}
