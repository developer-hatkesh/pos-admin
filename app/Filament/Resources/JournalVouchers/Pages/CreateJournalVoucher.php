<?php

declare(strict_types=1);

namespace App\Filament\Resources\JournalVouchers\Pages;

use App\Filament\Resources\JournalVouchers\JournalVoucherResource;
use App\Services\Accounting\JournalVoucherService;
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
        if ($this->record->form_type === 'credit_note') {
            $service->completeCreditNote($this->record);
        } else {
            $service->completeManual($this->record, $this->journalLines);
        }
    }

    protected function getRedirectUrl(): string
    {
        return JournalVoucherResource::getUrl('index');
    }
}
