<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\BankTransactionType;
use App\Enums\JournalSourceType;
use App\Models\BankTransaction;
use App\Services\Accounting\Concerns\FindsLedgers;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BankPostingService
{
    use FindsLedgers;

    public function __construct(private readonly JournalService $journals) {}

    public function post(BankTransaction $transaction): BankTransaction
    {
        if ($transaction->journal_id !== null) {
            throw new RuntimeException('Bank transaction is already posted.');
        }

        return DB::transaction(function () use ($transaction): BankTransaction {
            $transaction->loadMissing(['party.ledger', 'ledger']);

            $bankLedger = $this->ledgerByCode($transaction->company_id, '1200');
            $counterpartyLedger = $transaction->party?->ledger
                ?: $transaction->ledger
                ?: $this->ledgerByCode($transaction->company_id, $transaction->type === BankTransactionType::Deposit ? '1100' : '2100');

            $journal = $this->journals->createJournalEntry(
                $transaction->company_id,
                $transaction->transaction_date->toDateString(),
                JournalSourceType::Bank,
                $transaction->id,
                $transaction->reference,
                'Bank transaction '.$transaction->id,
            );

            if ($transaction->type === BankTransactionType::Deposit) {
                $this->journals->addLine($journal, $bankLedger, $transaction->amount, 0, 'Bank deposit');
                $this->journals->addLine($journal, $counterpartyLedger, 0, $transaction->amount, 'Deposit counterparty');
            } else {
                $this->journals->addLine($journal, $counterpartyLedger, $transaction->amount, 0, 'Withdrawal counterparty');
                $this->journals->addLine($journal, $bankLedger, 0, $transaction->amount, 'Bank withdrawal');
            }

            $this->journals->post($journal);
            $transaction->update(['journal_id' => $journal->id]);

            return $transaction->refresh();
        });
    }
}
