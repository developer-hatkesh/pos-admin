<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\InvoiceStatus;
use App\Enums\JournalSourceType;
use App\Enums\StockMovementType;
use App\Models\PurchaseInvoice;
use App\Services\Accounting\Concerns\FindsLedgers;
use App\Services\Inventory\StockMovementService;
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
            $this->recalculate($invoice);

            $purchaseLedger = $this->ledgerByCode($invoice->company_id, '5000');
            $vatInputLedger = $this->ledgerByCode($invoice->company_id, '2202');
            $supplierLedger = $invoice->supplier?->ledger ?: $invoice->party?->ledger ?: $this->ledgerByCode($invoice->company_id, '2100');

            $journal = $this->journals->createJournalEntry(
                $invoice->company_id,
                $invoice->invoice_date->toDateString(),
                JournalSourceType::Purchase,
                $invoice->id,
                $invoice->invoice_no,
                'Purchase invoice '.$invoice->invoice_no,
            );

            $this->journals->addLine($journal, $purchaseLedger, max(0, (float) $invoice->subtotal - (float) $invoice->discount), 0, 'Purchases');

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
                    'CANCEL-'.$invoice->invoice_no,
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

            return $invoice->refresh();
        });
    }

    public function recalculate(PurchaseInvoice $invoice): void
    {
        $subtotal = 0;
        $lines = [];

        foreach ($invoice->items as $line) {
            $net = round((float) $line->qty * (float) $line->rate, 2);
            $subtotal += $net;
            $lines[] = [
                'line' => $line,
                'net' => $net,
                'vat_rate' => (float) $line->vat_rate,
            ];
        }

        $discount = round(min(max((float) $invoice->discount, 0), $subtotal), 2);
        $vatTotal = 0;

        foreach ($lines as $lineData) {
            $discountShare = $subtotal > 0 ? round($discount * ($lineData['net'] / $subtotal), 2) : 0.0;
            $taxableNet = max(0, $lineData['net'] - $discountShare);
            $vat = round($taxableNet * ($lineData['vat_rate'] / 100), 2);
            $line = $lineData['line'];
            $line->forceFill(['vat_amount' => $vat, 'line_total' => $lineData['net'] + $vat])->save();
            $vatTotal += $vat;
        }

        $invoice->forceFill([
            'subtotal' => $subtotal,
            'discount' => $discount,
            'vat_total' => $vatTotal,
            'total' => max(0, $subtotal - $discount + $vatTotal),
        ])->save();
    }
}
