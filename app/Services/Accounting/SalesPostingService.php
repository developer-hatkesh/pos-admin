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
            $invoice->loadMissing(['party.ledger', 'items.item']);
            $this->recalculate($invoice);

            $customerLedger = $invoice->party->ledger ?: $this->ledgerByCode($invoice->company_id, '1100');
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
                if ($line->item?->stock_enabled) {
                    $this->stockMovements->create($line->item, StockMovementType::Out, $line->qty, $line->rate, $invoice->invoice_date->toDateString(), SalesInvoice::class, $invoice->id);
                }
            }

            $invoice->update(['journal_id' => $journal->id, 'status' => InvoiceStatus::Posted]);

            return $invoice->refresh();
        });
    }

    public function recalculate(SalesInvoice $invoice): void
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

        $discount = round((float) $invoice->discount, 2);
        $invoice->forceFill([
            'subtotal' => $subtotal,
            'vat_total' => $vatTotal,
            'total' => max(0, $subtotal - $discount + $vatTotal),
        ])->save();
    }
}
