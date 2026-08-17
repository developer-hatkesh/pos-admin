<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\BankTransactionType;
use App\Enums\JournalSourceType;
use App\Models\BankTransaction;
use App\Models\JournalEntry;
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
            $transaction->loadMissing(['bankAccount.ledger', 'customer.ledger', 'supplier.ledger', 'party.ledger', 'ledger']);

            $bankLedger = $transaction->bankAccount?->ledger ?: $this->ledgerByCode($transaction->company_id, '1200');
            $counterpartyLedger = $transaction->customer?->ledger
                ?: $transaction->supplier?->ledger
                ?: $transaction->party?->ledger
                ?: $transaction->ledger
                ?: ($transaction->type === BankTransactionType::Deposit
                    ? $this->receivableLedger($transaction->company_id)
                    : $this->ledgerByCode($transaction->company_id, '2100'));

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

    public function synchronizePosted(BankTransaction $transaction): BankTransaction
    {
        if ($transaction->journal_id === null) {
            throw new RuntimeException('Bank transaction has not been posted.');
        }

        if ($transaction->reconciled) {
            throw new RuntimeException('A reconciled bank transaction cannot be amended. Reverse it instead.');
        }

        return DB::transaction(function () use ($transaction): BankTransaction {
            $transaction->loadMissing(['bankAccount.ledger', 'customer.ledger', 'supplier.ledger', 'party.ledger', 'ledger']);
            $journal = JournalEntry::withoutGlobalScopes()
                ->with('journalLines')
                ->lockForUpdate()
                ->findOrFail($transaction->journal_id);

            if ($journal->journalLines->count() !== 2) {
                throw new RuntimeException('The posted bank journal does not have the expected two lines.');
            }

            $bankLedger = $transaction->bankAccount?->ledger ?: $this->ledgerByCode($transaction->company_id, '1200');
            $counterpartyLedger = $transaction->customer?->ledger
                ?: $transaction->supplier?->ledger
                ?: $transaction->party?->ledger
                ?: $transaction->ledger
                ?: ($transaction->type === BankTransactionType::Deposit
                    ? $this->receivableLedger($transaction->company_id)
                    : $this->ledgerByCode($transaction->company_id, '2100'));

            $journal->update([
                'entry_date' => $transaction->transaction_date,
                'reference' => $transaction->reference,
                'description' => 'Bank transaction '.$transaction->id,
            ]);

            [$first, $second] = $journal->journalLines->sortBy('id')->values()->all();

            if ($transaction->type === BankTransactionType::Deposit) {
                $first->update(['ledger_id' => $bankLedger->id, 'debit' => $transaction->amount, 'credit' => 0, 'description' => 'Bank deposit']);
                $second->update(['ledger_id' => $counterpartyLedger->id, 'debit' => 0, 'credit' => $transaction->amount, 'description' => 'Deposit counterparty']);
            } else {
                $first->update(['ledger_id' => $counterpartyLedger->id, 'debit' => $transaction->amount, 'credit' => 0, 'description' => 'Withdrawal counterparty']);
                $second->update(['ledger_id' => $bankLedger->id, 'debit' => 0, 'credit' => $transaction->amount, 'description' => 'Bank withdrawal']);
            }

            return $transaction->refresh();
        });
    }
}
