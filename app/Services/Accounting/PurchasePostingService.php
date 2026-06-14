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

            $this->journals->addLine($journal, $purchaseLedger, $invoice->subtotal, 0, 'Purchases');

            if ((float) $invoice->vat_total > 0) {
                $this->journals->addLine($journal, $vatInputLedger, $invoice->vat_total, 0, 'VAT input');
            }

            $this->journals->addLine($journal, $supplierLedger, 0, $invoice->total, 'Supplier payable');
            $this->journals->post($journal);

            foreach ($invoice->items as $line) {
                if ($line->productItem?->stock_enabled) {
                    $this->stockMovements->create($line->productItem, StockMovementType::In, $line->qty, $line->rate, $invoice->invoice_date->toDateString(), PurchaseInvoice::class, $invoice->id);
                }
            }

            $invoice->update(['journal_id' => $journal->id, 'status' => InvoiceStatus::Posted]);

            return $invoice->refresh();
        });
    }

    public function recalculate(PurchaseInvoice $invoice): void
    {
        $subtotal = 0;
        $vatTotal = 0;

        foreach ($invoice->items as $line) {
            $net = round((float) $line->qty * (float) $line->rate, 2);
            $vat = round($net * ((float) $line->vat_rate / 100), 2);
            $line->forceFill(['vat_amount' => $vat, 'line_total' => $net + $vat])->save();
            $subtotal += $net;
            $vatTotal += $vat;
        }

        $invoice->forceFill([
            'subtotal' => $subtotal,
            'vat_total' => $vatTotal,
            'total' => $subtotal + $vatTotal,
        ])->save();
    }
}
