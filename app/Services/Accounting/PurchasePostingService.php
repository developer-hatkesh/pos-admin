<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\InvoiceStatus;
use App\Enums\JournalSourceType;
use App\Enums\StockMovementType;
use App\Models\PurchaseInvoice;
use App\Services\Accounting\Concerns\FindsLedgers;
use App\Services\Inventory\StockMovementService;
use App\Support\DocumentTotals;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PurchasePostingService
{
    use FindsLedgers;

    public function __construct(
        private readonly JournalService $journals,
        private readonly StockMovementService $stockMovements,
    ) {}

    public function post(PurchaseInvoice $invoice): PurchaseInvoice
    {
        if ($invoice->journal_id !== null || $invoice->status === InvoiceStatus::Posted) {
            throw new RuntimeException('Purchase invoice is already posted.');
        }

        return DB::transaction(function () use ($invoice): PurchaseInvoice {
            $invoice->loadMissing(['supplier.ledger', 'party.ledger', 'items.productItem', 'items.item']);

            if ($invoice->items->isEmpty()) {
                throw new RuntimeException('A purchase invoice must contain at least one item.');
            }

            foreach ($invoice->items as $item) {
                if ((float) $item->rate <= 0 || (float) $item->qty <= 0) {
                    throw new RuntimeException('Purchase invoice item price and quantity must be greater than zero.');
                }
            }

            $this->recalculate($invoice);

            $purchaseLedger = $this->ledgerByCode($invoice->company_id, '5000');
            $vatInputLedger = $this->ledgerByCode($invoice->company_id, '2202');
            $supplierLedger = $invoice->supplier?->ledger ?: $invoice->party?->ledger ?: $this->ledgerByCode($invoice->company_id, '2100');
            $voucherNumber = $invoice->voucherNumber();
            $supplierReference = $invoice->supplierInvoiceNumber();

            $journal = $this->journals->createJournalEntry(
                $invoice->company_id,
                $invoice->invoice_date->toDateString(),
                JournalSourceType::Purchase,
                $invoice->id,
                $voucherNumber,
                'Purchase invoice '.$voucherNumber.($supplierReference ? ' (Supplier invoice '.$supplierReference.')' : ''),
            );

            $this->journals->addLine($journal, $purchaseLedger, max(0, (float) $invoice->subtotal - (float) $invoice->discount + (float) $invoice->shipping), 0, 'Purchases and shipping');

            if ((float) $invoice->vat_total > 0) {
                $this->journals->addLine($journal, $vatInputLedger, $invoice->vat_total, 0, 'VAT input');
            }

            $this->journals->addLine($journal, $supplierLedger, 0, $invoice->total, 'Supplier payable');
            $this->journals->post($journal);

            foreach ($invoice->items as $line) {
                if ($line->productItem?->stock_enabled) {
                    $this->stockMovements->create($line->productItem, StockMovementType::Purchase, $line->qty, $line->rate, $invoice->invoice_date->toDateString(), PurchaseInvoice::class, $invoice->id);
                }
            }

            $invoice->update(['journal_id' => $journal->id, 'status' => InvoiceStatus::Posted]);

            activity('business')
                ->event('posted')
                ->performedOn($invoice)
                ->withProperties(['journal_id' => $journal->id, 'voucher_no' => $voucherNumber, 'supplier_invoice_no' => $supplierReference])
                ->log('PurchaseInvoice '.$voucherNumber.' posted');

            return $invoice->refresh();
        });
    }

    public function cancel(PurchaseInvoice $invoice): PurchaseInvoice
    {
        if ($invoice->status !== InvoiceStatus::Posted) {
            throw new RuntimeException('Only posted purchase invoices can be cancelled.');
        }

        return DB::transaction(function () use ($invoice): PurchaseInvoice {
            $invoice->loadMissing(['journalEntry.journalLines', 'items.productItem']);

            if ($invoice->journalEntry !== null) {
                $this->journals->reverse(
                    $invoice->journalEntry,
                    now()->toDateString(),
                    'CANCEL-'.$invoice->voucherNumber(),
                );
            }

            foreach ($invoice->items as $line) {
                if ($line->productItem?->stock_enabled) {
                    $this->stockMovements->create(
                        $line->productItem,
                        StockMovementType::PurchaseReturn,
                        $line->qty,
                        $line->rate,
                        now()->toDateString(),
                        PurchaseInvoice::class,
                        $invoice->id,
                    );
                }
            }

            $invoice->update(['status' => InvoiceStatus::Cancelled]);

            activity('business')
                ->event('cancelled')
                ->performedOn($invoice)
                ->withProperties(['voucher_no' => $invoice->voucherNumber(), 'supplier_invoice_no' => $invoice->supplierInvoiceNumber()])
                ->log('PurchaseInvoice '.$invoice->voucherNumber().' cancelled');

            return $invoice->refresh();
        });
    }

    public function recalculate(PurchaseInvoice $invoice): void
    {
        $data = DocumentTotals::calculate(['items' => $invoice->items->toArray(), 'discount' => $invoice->discount, 'shipping' => $invoice->shipping]);

        foreach ($invoice->items as $index => $line) {
            $line->forceFill([
                'vat_rate' => $data['items'][$index]['vat_rate'],
                'vat_amount' => $data['items'][$index]['vat_amount'],
                'line_total' => $data['items'][$index]['line_total'],
            ])->save();
        }

        $invoice->forceFill(collect($data)->only(['subtotal', 'discount', 'vat_total', 'shipping', 'total'])->all())->save();
    }
}
