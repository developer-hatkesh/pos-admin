<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\VatReturnStatus;
use App\Models\JournalLine;
use App\Models\VatReturn;
use App\Services\Accounting\Concerns\FindsLedgers;

class VatReturnService
{
    use FindsLedgers;

    public function calculate(int $companyId, string $periodStart, string $periodEnd): array
    {
        $vatOutput = $this->ledgerByCode($companyId, '2201');
        $vatInput = $this->ledgerByCode($companyId, '2202');
        $sales = $this->ledgerByCode($companyId, '4000');
        $purchases = $this->ledgerByCode($companyId, '5000');

        $box1 = $this->ledgerCreditNet($vatOutput->id, $periodStart, $periodEnd);
        $box4 = $this->ledgerDebitNet($vatInput->id, $periodStart, $periodEnd);
        $box6 = $this->ledgerCreditNet($sales->id, $periodStart, $periodEnd);
        $box7 = $this->ledgerDebitNet($purchases->id, $periodStart, $periodEnd);

        return [
            'box1' => $box1,
            'box2' => 0,
            'box4' => $box4,
            'box6' => $box6,
            'box7' => $box7,
            'box8' => 0,
            'box9' => 0,
        ];
    }

    public function generate(int $companyId, string $periodStart, string $periodEnd): VatReturn
    {
        return VatReturn::query()->updateOrCreate(
            ['company_id' => $companyId, 'period_start' => $periodStart, 'period_end' => $periodEnd],
            [...$this->calculate($companyId, $periodStart, $periodEnd), 'status' => VatReturnStatus::Draft],
        );
    }

    private function ledgerCreditNet(int $ledgerId, string $periodStart, string $periodEnd): float
    {
        $row = $this->baseLines($ledgerId, $periodStart, $periodEnd)->selectRaw('COALESCE(SUM(credit - debit), 0) as amount')->first();

        return round((float) $row->amount, 2);
    }

    private function ledgerDebitNet(int $ledgerId, string $periodStart, string $periodEnd): float
    {
        $row = $this->baseLines($ledgerId, $periodStart, $periodEnd)->selectRaw('COALESCE(SUM(debit - credit), 0) as amount')->first();

        return round((float) $row->amount, 2);
    }

    private function baseLines(int $ledgerId, string $periodStart, string $periodEnd)
    {
        return JournalLine::query()
            ->where('ledger_id', $ledgerId)
            ->whereHas('journalEntry', fn ($query) => $query->whereBetween('entry_date', [$periodStart, $periodEnd]));
    }
}
