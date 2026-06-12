<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\JournalSourceType;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Ledger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class JournalService
{
    public function createJournalEntry(int $companyId, string $entryDate, JournalSourceType $sourceType, ?int $sourceId = null, ?string $reference = null, ?string $description = null): JournalEntry
    {
        return JournalEntry::query()->create([
            'company_id' => $companyId,
            'entry_date' => $entryDate,
            'reference' => $reference,
            'description' => $description,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'created_by' => auth()->id(),
        ]);
    }

    public function addLine(JournalEntry $journalEntry, Ledger|int $ledger, float|string $debit = 0, float|string $credit = 0, ?string $description = null): JournalLine
    {
        $debit = round((float) $debit, 2);
        $credit = round((float) $credit, 2);

        if (($debit <= 0 && $credit <= 0) || ($debit > 0 && $credit > 0)) {
            throw new InvalidArgumentException('A journal line must contain either a debit or a credit amount.');
        }

        return $journalEntry->journalLines()->create([
            'ledger_id' => $ledger instanceof Ledger ? $ledger->id : $ledger,
            'debit' => $debit,
            'credit' => $credit,
            'description' => $description,
        ]);
    }

    public function validateBalanced(JournalEntry $journalEntry): void
    {
        $debit = round((float) $journalEntry->journalLines()->sum('debit'), 2);
        $credit = round((float) $journalEntry->journalLines()->sum('credit'), 2);

        if ($debit !== $credit) {
            throw new InvalidArgumentException("Journal entry {$journalEntry->id} is not balanced. Debit {$debit}, credit {$credit}.");
        }
    }

    public function post(JournalEntry $journalEntry): JournalEntry
    {
        $this->validateBalanced($journalEntry);

        return $journalEntry->refresh();
    }

    public function reverse(JournalEntry $journalEntry, ?string $entryDate = null, ?string $reference = null): JournalEntry
    {
        return DB::transaction(function () use ($journalEntry, $entryDate, $reference): JournalEntry {
            $reversal = $this->createJournalEntry(
                $journalEntry->company_id,
                $entryDate ?? now()->toDateString(),
                JournalSourceType::Manual,
                $journalEntry->id,
                $reference ?? 'REV-'.$journalEntry->reference,
                'Reversal of journal entry '.$journalEntry->id,
            );

            foreach ($journalEntry->journalLines as $line) {
                $this->addLine($reversal, $line->ledger_id, $line->credit, $line->debit, 'Reversal: '.$line->description);
            }

            return $this->post($reversal);
        });
    }
}
