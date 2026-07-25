<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\InvoiceStatus;
use App\Enums\JournalSourceType;
use App\Models\JournalVoucher;
use App\Models\Ledger;
use App\Models\SalesInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JournalVoucherService
{
    public function __construct(private readonly JournalService $journals) {}

    public function completeCreditNote(JournalVoucher $voucher): void
    {
        DB::transaction(function () use ($voucher): void {
            $voucher->loadMissing(['salesReturn', 'allocations.salesInvoice']);
            $return = $voucher->salesReturn;

            if (! $return || $return->company_id !== $voucher->company_id || $return->journal_id === null) {
                throw ValidationException::withMessages(['data.sales_return_id' => 'Select a posted Credit Note.']);
            }

            $allocated = round((float) $voucher->allocations->sum('amount'), 2);
            if ($allocated <= 0 || $allocated > round((float) $return->total, 2)) {
                throw ValidationException::withMessages(['data.allocations' => 'The allocation must be greater than zero and cannot exceed the Credit Note total.']);
            }

            foreach ($voucher->allocations as $index => $allocation) {
                $invoice = $allocation->salesInvoice;
                if (! $invoice || $invoice->company_id !== $voucher->company_id || $invoice->customer_id !== $return->customer_id) {
                    throw ValidationException::withMessages(["data.allocations.{$index}.sales_invoice_id" => 'The invoice must belong to the Credit Note customer.']);
                }

                $other = (float) $invoice->journalVoucherAllocations()
                    ->where('journal_voucher_id', '!=', $voucher->id)
                    ->sum('amount');
                $receipts = (float) $invoice->allocations()->sum('amount');
                $available = round(max(0, (float) $invoice->total - $other - $receipts), 2);
                if ((float) $allocation->amount <= 0 || round((float) $allocation->amount, 2) > $available) {
                    throw ValidationException::withMessages(["data.allocations.{$index}.amount" => 'The allocation exceeds the invoice outstanding balance.']);
                }
            }

            $voucher->update(['journal_id' => $return->journal_id]);
            $this->journals->validateBalanced($return->journalEntry);
            $this->syncInvoiceStatuses($voucher->allocations->pluck('sales_invoice_id'));
        });
    }

    public function completeManual(JournalVoucher $voucher, array $lines): void
    {
        DB::transaction(function () use ($voucher, $lines): void {
            $debit = round((float) collect($lines)->sum('debit'), 2);
            $credit = round((float) collect($lines)->sum('credit'), 2);

            if (count($lines) < 2 || $debit <= 0 || $debit !== $credit) {
                throw ValidationException::withMessages(['data.journal_lines' => 'Total debit and credit must match and be greater than zero.']);
            }

            $journal = $this->journals->createJournalEntry(
                $voucher->company_id,
                $voucher->voucher_date->toDateString(),
                JournalSourceType::Manual,
                $voucher->id,
                $voucher->voucher_no,
                $voucher->narration,
            );

            foreach ($lines as $index => $line) {
                try {
                    $ledger = Ledger::query()
                        ->whereKey((int) ($line['ledger_id'] ?? 0))
                        ->where('company_id', $voucher->company_id)
                        ->firstOrFail();
                    $this->journals->addLine($journal, $ledger, $line['debit'] ?? 0, $line['credit'] ?? 0, $line['particulars'] ?? null);
                } catch (\Throwable $exception) {
                    throw ValidationException::withMessages(["data.journal_lines.{$index}" => $exception->getMessage()]);
                }
            }

            $this->journals->post($journal);
            $voucher->update(['journal_id' => $journal->id]);
        });
    }

    public function syncInvoiceStatuses(iterable $invoiceIds): void
    {
        foreach (collect($invoiceIds)->filter()->unique() as $invoiceId) {
            $invoice = SalesInvoice::withoutGlobalScopes()->find((int) $invoiceId);
            if (! $invoice || $invoice->status === InvoiceStatus::Cancelled) {
                continue;
            }

            $receipts = (float) $invoice->allocations()->sum('amount');
            $credits = (float) $invoice->journalVoucherAllocations()->sum('amount');
            $settled = round($receipts + $credits, 2);
            $outstanding = round(max(0, (float) $invoice->total - $settled), 2);
            $invoice->update(['status' => $outstanding <= 0 ? InvoiceStatus::Paid : ($settled > 0 ? InvoiceStatus::Partial : InvoiceStatus::Posted)]);
        }
    }
}
