<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\InvoiceStatus;
use App\Enums\JournalSourceType;
use App\Enums\StockMovementType;
use App\Models\SalesInvoice;
use App\Services\Accounting\Concerns\FindsLedgers;
use App\Services\Inventory\StockMovementService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SalesPostingService
{
    use FindsLedgers;

    public function __construct(
        private readonly JournalService $journals,
        private readonly StockMovementService $stockMovements,
    ) {}

    public function post(SalesInvoice $invoice): SalesInvoice
    {
        if ($invoice->journal_id !== null || $invoice->status === InvoiceStatus::Posted) {
            throw new RuntimeException('Sales invoice is already posted.');
        }

        return DB::transaction(function () use ($invoice): SalesInvoice {
            $invoice->loadMissing(['customer.ledger', 'party.ledger', 'items.productItem', 'items.item']);
            $this->recalculate($invoice);

            $customerLedger = $invoice->customer?->ledger ?: $invoice->party?->ledger ?: $this->ledgerByCode($invoice->company_id, '1100');
            $salesLedger = $this->ledgerByCode($invoice->company_id, '4000');
            $vatOutputLedger = $this->ledgerByCode($invoice->company_id, '2201');

            $journal = $this->journals->createJournalEntry(
                $invoice->company_id,
                $invoice->invoice_date->toDateString(),
                JournalSourceType::Sales,
                $invoice->id,
                $invoice->invoice_no,
                'Sales invoice '.$invoice->invoice_no,
            );

            $this->journals->addLine($journal, $customerLedger, $invoice->total, 0, 'Customer receivable');
            $this->journals->addLine($journal, $salesLedger, 0, $invoice->subtotal - $invoice->discount, 'Sales income');

            if ((float) $invoice->vat_total > 0) {
                $this->journals->addLine($journal, $vatOutputLedger, 0, $invoice->vat_total, 'VAT output');
            }

            $this->journals->post($journal);

            foreach ($invoice->items as $line) {
                if ($line->productItem?->stock_enabled) {
                    $this->stockMovements->create($line->productItem, StockMovementType::Sale, $line->qty, $line->rate, $invoice->invoice_date->toDateString(), SalesInvoice::class, $invoice->id);
                }
            }

            $invoice->update(['journal_id' => $journal->id, 'status' => InvoiceStatus::Posted]);

            return $invoice->refresh();
        });
    }

    public function cancel(SalesInvoice $invoice): SalesInvoice
    {
        if ($invoice->status !== InvoiceStatus::Posted) {
            throw new RuntimeException('Only posted sales invoices can be cancelled.');
        }

        return DB::transaction(function () use ($invoice): SalesInvoice {
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
                        StockMovementType::SalesReturn,
                        $line->qty,
                        $line->rate,
                        now()->toDateString(),
                        SalesInvoice::class,
                        $invoice->id,
                    );
                }
            }

            $invoice->update(['status' => InvoiceStatus::Cancelled]);

            return $invoice->refresh();
        });
    }

    public function recalculate(SalesInvoice $invoice): void
    {
        $subtotal = 0;

        foreach ($invoice->items as $line) {
            $net = round((float) $line->qty * (float) $line->rate, 2);
            $subtotal += $net;
        }

        $discount = round(min((float) $invoice->discount, $subtotal), 2);
        $vatTotal = 0;

        foreach ($invoice->items as $line) {
            $net = round((float) $line->qty * (float) $line->rate, 2);
            $discountShare = $subtotal > 0 ? round($discount * ($net / $subtotal), 2) : 0;
            $taxableNet = max(0, $net - $discountShare);
            $vat = round($taxableNet * ((float) $line->vat_rate / 100), 2);

            $line->forceFill(['vat_amount' => $vat, 'line_total' => $net + $vat])->save();
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
