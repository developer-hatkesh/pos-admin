<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\InvoiceStatus;
use App\Enums\JournalSourceType;
use App\Enums\StockMovementType;
use App\Models\SalesInvoice;
use App\Services\Accounting\Concerns\FindsLedgers;
use App\Services\Inventory\StockMovementService;
use App\Support\DocumentTotals;
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

            if ($invoice->items->isEmpty()) {
                throw new RuntimeException('A sales invoice must contain at least one item.');
            }

            foreach ($invoice->items as $item) {
                if ((float) $item->rate <= 0 || (float) $item->qty <= 0) {
                    throw new RuntimeException('Sales invoice item price and quantity must be greater than zero.');
                }
            }

            $this->recalculate($invoice);

            $customerLedger = $invoice->customer?->ledger ?: $invoice->party?->ledger ?: $this->receivableLedger($invoice->company_id);
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
            $this->journals->addLine($journal, $salesLedger, 0, $invoice->subtotal - $invoice->discount + $invoice->shipping, 'Sales and shipping income');

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

            activity('business')
                ->event('posted')
                ->performedOn($invoice)
                ->withProperties(['journal_id' => $journal->id, 'invoice_no' => $invoice->invoice_no])
                ->log('SalesInvoice '.$invoice->invoice_no.' posted');

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

            activity('business')
                ->event('cancelled')
                ->performedOn($invoice)
                ->withProperties(['invoice_no' => $invoice->invoice_no])
                ->log('SalesInvoice '.$invoice->invoice_no.' cancelled');

            return $invoice->refresh();
        });
    }

    public function recalculate(SalesInvoice $invoice): void
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
