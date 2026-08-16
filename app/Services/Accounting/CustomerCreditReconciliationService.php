<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\InvoiceStatus;
use App\Enums\SalesReturnStatus;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\Customer;
use App\Models\JournalVoucher;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;

class CustomerCreditReconciliationService
{
    public function reconcile(Customer $customer): array
    {
        return DB::transaction(function () use ($customer): array {
            $invoiceIds = [];
            $receiptAllocated = 0.0;
            $creditNoteAllocated = 0.0;

            foreach ($this->outstandingInvoices($customer) as $invoice) {
                $available = $this->invoiceOutstanding($invoice);

                foreach ($this->unallocatedReceipts($customer) as $receipt) {
                    if ($available <= 0) {
                        break;
                    }

                    $unallocated = $this->receiptUnallocated($receipt);
                    if ($unallocated <= 0) {
                        continue;
                    }

                    $amount = round(min($available, $unallocated), 2);
                    $allocation = $receipt->allocations()
                        ->firstOrNew(['sales_invoice_id' => $invoice->id]);
                    $allocation->amount = round((float) ($allocation->amount ?? 0) + $amount, 2);
                    $allocation->save();
                    $receiptAllocated = round($receiptAllocated + $amount, 2);
                    $available = round($available - $amount, 2);
                    $invoiceIds[] = $invoice->id;
                }

                foreach ($this->unallocatedCreditNotes($customer) as $return) {
                    if ($available <= 0) {
                        break;
                    }

                    $unallocated = $this->creditNoteUnallocated($return);
                    if ($unallocated <= 0) {
                        continue;
                    }

                    $journalVoucher = $return->journalVoucher;
                    if ($journalVoucher === null) {
                        $journalVoucher = JournalVoucher::query()->create([
                            'company_id' => $return->company_id,
                            'voucher_date' => $return->return_date,
                            'form_type' => 'credit_note',
                            'sales_return_id' => $return->id,
                            'journal_id' => $return->journal_id,
                            'narration' => 'Credit Note '.$return->return_no.' automatically allocated against sales invoice',
                        ]);
                    }

                    $amount = round(min($available, $unallocated), 2);
                    $allocation = $journalVoucher->allocations()
                        ->firstOrNew(['sales_invoice_id' => $invoice->id]);
                    $allocation->amount = round((float) ($allocation->amount ?? 0) + $amount, 2);
                    $allocation->save();
                    $creditNoteAllocated = round($creditNoteAllocated + $amount, 2);
                    $available = round($available - $amount, 2);
                    $invoiceIds[] = $invoice->id;
                    $return->unsetRelation('journalVoucher');
                }
            }

            app(VoucherPostingService::class)->syncSalesInvoiceStatuses($invoiceIds);

            return [
                'receipt_allocated' => $receiptAllocated,
                'credit_note_allocated' => $creditNoteAllocated,
                'total_allocated' => round($receiptAllocated + $creditNoteAllocated, 2),
                'invoice_count' => count(array_unique($invoiceIds)),
            ];
        });
    }

    private function outstandingInvoices(Customer $customer)
    {
        return $customer->salesInvoices()
            ->whereIn('status', [InvoiceStatus::Posted->value, InvoiceStatus::Partial->value])
            ->orderBy('due_date')
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get()
            ->filter(fn (SalesInvoice $invoice): bool => $this->invoiceOutstanding($invoice) > 0);
    }

    private function unallocatedReceipts(Customer $customer)
    {
        return $customer->vouchers()
            ->where('voucher_type', VoucherType::Receipt->value)
            ->where('status', VoucherStatus::Posted->value)
            ->with('allocations')
            ->orderBy('voucher_date')
            ->orderBy('id')
            ->get()
            ->filter(fn (Voucher $voucher): bool => $this->receiptUnallocated($voucher) > 0);
    }

    private function unallocatedCreditNotes(Customer $customer)
    {
        return $customer->salesReturns()
            ->where('status', SalesReturnStatus::Posted->value)
            ->whereNotNull('journal_id')
            ->with('journalVoucher.allocations')
            ->orderBy('return_date')
            ->orderBy('id')
            ->get()
            ->filter(fn (SalesReturn $return): bool => $this->creditNoteUnallocated($return) > 0);
    }

    private function invoiceOutstanding(SalesInvoice $invoice): float
    {
        $receipts = (float) $invoice->allocations()
            ->whereHas('voucher', fn ($query) => $query->where('status', VoucherStatus::Posted->value))
            ->sum('amount');
        $credits = (float) $invoice->journalVoucherAllocations()->sum('amount');

        return round(max(0, (float) $invoice->total - $receipts - $credits), 2);
    }

    private function receiptUnallocated(Voucher $voucher): float
    {
        return round(max(0, (float) $voucher->amount - (float) $voucher->allocations->sum('amount')), 2);
    }

    private function creditNoteUnallocated(SalesReturn $return): float
    {
        return round(max(0, (float) $return->total - (float) ($return->journalVoucher?->allocations->sum('amount') ?? 0)), 2);
    }
}
