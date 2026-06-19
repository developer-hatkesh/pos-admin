<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\ExpenseStatus;
use App\Enums\JournalSourceType;
use App\Models\Expense;
use App\Services\Accounting\Concerns\FindsLedgers;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExpensePostingService
{
    use FindsLedgers;

    public function __construct(private readonly JournalService $journals) {}

    public function post(Expense $expense): Expense
    {
        if ($expense->journal_id !== null || $expense->status === ExpenseStatus::Posted) {
            throw new RuntimeException('Expense is already posted.');
        }

        return DB::transaction(function () use ($expense): Expense {
            $expense->loadMissing(['category.ledger', 'supplier.ledger']);
            $this->recalculate($expense);

            $expenseLedger = $expense->category?->ledger;
            $vatInputLedger = $this->ledgerByCode($expense->company_id, '2202');
            $payableLedger = $expense->supplier?->ledger ?: $this->ledgerByCode($expense->company_id, '2100');

            if (! $expenseLedger) {
                throw new RuntimeException('Expense category ledger is missing.');
            }

            $journal = $this->journals->createJournalEntry(
                $expense->company_id,
                $expense->expense_date->toDateString(),
                JournalSourceType::Expense,
                $expense->id,
                $expense->voucher_no,
                'Expense '.$expense->voucher_no,
            );

            $this->journals->addLine($journal, $expenseLedger, $expense->sub_total_amount, 0, 'Expense');

            if ((float) $expense->tax_amount > 0) {
                $this->journals->addLine($journal, $vatInputLedger, $expense->tax_amount, 0, 'VAT input');
            }

            $this->journals->addLine($journal, $payableLedger, 0, $expense->grand_total_amount, 'Supplier payable');
            $this->journals->post($journal);

            $expense->update(['journal_id' => $journal->id, 'status' => ExpenseStatus::Posted]);

            return $expense->refresh();
        });
    }

    public function recalculate(Expense $expense): void
    {
        $subtotal = round((float) $expense->sub_total_amount, 2);
        $tax = round((float) $expense->tax_amount, 2);

        $expense->forceFill([
            'grand_total_amount' => max(0, $subtotal + $tax),
        ])->save();
    }
}
