<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\JournalSourceType;
use App\Enums\SalesReturnStatus;
use App\Enums\StockMovementType;
use App\Models\SalesInvoiceItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Services\Accounting\Concerns\FindsLedgers;
use App\Services\Inventory\StockMovementService;
use App\Support\DocumentTotals;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SalesReturnPostingService
{
    use FindsLedgers;

    public function __construct(
        private readonly JournalService $journals,
        private readonly StockMovementService $stockMovements,
    ) {}

    public function post(SalesReturn $return): SalesReturn
    {
        if ($return->journal_id !== null || $return->status === SalesReturnStatus::Posted) {
            throw new RuntimeException('Credit note is already posted.');
        }

        return DB::transaction(function () use ($return): SalesReturn {
            $return->loadMissing(['customer.ledger', 'salesInvoices', 'items.productItem', 'items.salesInvoiceItem']);
            $this->validateReturnQuantities($return);
            $this->recalculate($return);

            $customerLedger = $return->customer?->ledger ?: $this->receivableLedger($return->company_id);
            $salesLedger = $this->ledgerByCode($return->company_id, '4000');
            $vatOutputLedger = $this->ledgerByCode($return->company_id, '2201');

            $journal = $this->journals->createJournalEntry(
                $return->company_id,
                $return->return_date->toDateString(),
                JournalSourceType::CreditNote,
                $return->id,
                $return->return_no,
                'Credit note '.$return->return_no,
            );

            $this->journals->addLine($journal, $salesLedger, $return->subtotal + $return->shipping, 0, 'Credit note including shipping refund');

            if ((float) $return->vat_total > 0) {
                $this->journals->addLine($journal, $vatOutputLedger, $return->vat_total, 0, 'VAT output reversal');
            }

            $this->journals->addLine($journal, $customerLedger, 0, $return->total, 'Customer credit');
            $this->journals->post($journal);

            $this->syncStockMovements($return);

            $return->update(['journal_id' => $journal->id, 'status' => SalesReturnStatus::Posted]);

            activity('business')
                ->event('posted')
                ->performedOn($return)
                ->withProperties(['journal_id' => $journal->id, 'return_no' => $return->return_no])
                ->log('SalesReturn '.$return->return_no.' posted');

            return $return->refresh();
        });
    }

    public function recalculate(SalesReturn $return): void
    {
        $data = DocumentTotals::calculate(['items' => $return->items->toArray(), 'shipping' => $return->shipping], false);

        foreach ($return->items as $index => $line) {
            $line->forceFill([
                'vat_rate' => $data['items'][$index]['vat_rate'],
                'vat_amount' => $data['items'][$index]['vat_amount'],
                'line_total' => $data['items'][$index]['line_total'],
            ])->save();
        }

        $return->forceFill(collect($data)->only(['subtotal', 'vat_total', 'shipping', 'total'])->all())->save();

        if ($return->status === SalesReturnStatus::Posted) {
            $this->syncStockMovements($return);
        }
    }

    private function syncStockMovements(SalesReturn $return): void
    {
        $this->stockMovements->deleteForReference(SalesReturn::class, $return->id);
        $return->load('items.productItem');

        foreach ($return->items as $line) {
            if ($line->productItem?->stock_enabled) {
                $this->stockMovements->create($line->productItem, StockMovementType::SalesReturn, $line->qty, $line->rate, $return->return_date->toDateString(), SalesReturn::class, $return->id);
            }
        }
    }

    private function validateReturnQuantities(SalesReturn $return): void
    {
        foreach ($return->items as $line) {
            $matchingLineIds = $this->matchingInvoiceLineIds($return, $line);
            $soldQty = (float) SalesInvoiceItem::query()
                ->whereIn('id', $matchingLineIds)
                ->sum('qty');
            $alreadyReturned = (float) SalesReturnItem::query()
                ->whereIn('sales_invoice_item_id', $matchingLineIds)
                ->where('sales_return_id', '!=', $return->id)
                ->whereHas('salesReturn', fn ($query) => $query->where('status', SalesReturnStatus::Posted->value))
                ->sum('qty');
            $remaining = round($soldQty - $alreadyReturned, 3);

            if ((float) $line->qty > $remaining) {
                throw new RuntimeException('Return quantity for '.$line->description.' exceeds remaining sold quantity.');
            }
        }
    }

    private function matchingInvoiceLineIds(SalesReturn $return, SalesReturnItem $line): array
    {
        $source = $line->salesInvoiceItem;

        if (! $source) {
            return [];
        }

        $invoiceIds = $return->salesInvoices->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        if ($invoiceIds === [] && $return->sales_invoice_id !== null) {
            $invoiceIds = [(int) $return->sales_invoice_id];
        }

        return SalesInvoiceItem::query()
            ->whereIn('invoice_id', $invoiceIds)
            ->where('product_item_id', $source->product_item_id)
            ->where('description', $source->description)
            ->where('rate', $source->rate)
            ->where('tax_rate_id', $source->tax_rate_id)
            ->where('vat_rate', $source->vat_rate)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }
}
