<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\BankTransactionType;
use App\Enums\InvoiceStatus;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\BankTransaction;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VoucherPostingService
{
    public function __construct(private readonly BankPostingService $bankPosting) {}

    public function post(Voucher $voucher): Voucher
    {
        if ($voucher->status === VoucherStatus::Posted || $voucher->bank_transaction_id !== null) {
            throw new RuntimeException('Voucher is already posted.');
        }

        return DB::transaction(function () use ($voucher): Voucher {
            $voucher->loadMissing(['bankAccount', 'customer.ledger', 'supplier.ledger']);

            $transaction = BankTransaction::query()->create([
                'bank_account_id' => $voucher->bank_account_id,
                'company_id' => $voucher->company_id,
                'transaction_date' => $voucher->voucher_date,
                'type' => $voucher->voucher_type === VoucherType::Receipt
                    ? BankTransactionType::Deposit
                    : BankTransactionType::Withdrawal,
                'amount' => $voucher->amount,
                'reference' => $voucher->voucher_no.($voucher->reference_no ? ' / '.$voucher->reference_no : ''),
                'customer_id' => $voucher->voucher_type === VoucherType::Receipt ? $voucher->customer_id : null,
                'supplier_id' => $voucher->voucher_type === VoucherType::Payment ? $voucher->supplier_id : null,
                'ledger_id' => $voucher->voucher_type === VoucherType::Receipt
                    ? $voucher->customer?->ledger_id
                    : $voucher->supplier?->ledger_id,
            ]);

            $this->bankPosting->post($transaction);

            $voucher->update([
                'bank_transaction_id' => $transaction->id,
                'journal_id' => $transaction->refresh()->journal_id,
                'status' => VoucherStatus::Posted,
            ]);

            $this->syncAllocatedInvoiceStatuses($voucher->refresh());

            return $voucher->refresh();
        });
    }

    private function syncAllocatedInvoiceStatuses(Voucher $voucher): void
    {
        $voucher->loadMissing(['allocations.salesInvoice', 'allocations.purchaseInvoice']);

        foreach ($voucher->allocations as $allocation) {
            if ($allocation->salesInvoice !== null) {
                $this->syncSalesInvoiceStatus($allocation->salesInvoice);
            }

            if ($allocation->purchaseInvoice !== null) {
                $this->syncPurchaseInvoiceStatus($allocation->purchaseInvoice);
            }
        }
    }

    private function syncSalesInvoiceStatus(SalesInvoice $invoice): void
    {
        if ($invoice->status === InvoiceStatus::Cancelled) {
            return;
        }

        $paid = (float) $invoice->allocations()
            ->whereHas('voucher', fn ($query) => $query->where('status', VoucherStatus::Posted->value))
            ->sum('amount');

        $returned = (float) $invoice->salesReturns()->sum('total');
        $outstanding = round(max(0, (float) $invoice->total - $returned - $paid), 2);

        $invoice->update(['status' => $this->invoiceStatusForOutstanding($outstanding, $paid)]);
    }

    private function syncPurchaseInvoiceStatus(PurchaseInvoice $invoice): void
    {
        if ($invoice->status === InvoiceStatus::Cancelled) {
            return;
        }

        $paid = (float) $invoice->allocations()
            ->whereHas('voucher', fn ($query) => $query->where('status', VoucherStatus::Posted->value))
            ->sum('amount');

        $outstanding = round(max(0, (float) $invoice->total - $paid), 2);

        $invoice->update(['status' => $this->invoiceStatusForOutstanding($outstanding, $paid)]);
    }

    private function invoiceStatusForOutstanding(float $outstanding, float $paid): InvoiceStatus
    {
        if ($outstanding <= 0.0) {
            return InvoiceStatus::Paid;
        }

        if ($paid > 0.0) {
            return InvoiceStatus::Partial;
        }

        return InvoiceStatus::Posted;
    }
}
