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
            throw new RuntimeException('Sales return is already posted.');
        }

        return DB::transaction(function () use ($return): SalesReturn {
            $return->loadMissing(['customer.ledger', 'salesInvoices', 'items.productItem', 'items.salesInvoiceItem']);
            $this->validateReturnQuantities($return);
            $this->recalculate($return);

            $customerLedger = $return->customer?->ledger ?: $this->ledgerByCode($return->company_id, '1100');
            $salesLedger = $this->ledgerByCode($return->company_id, '4000');
            $vatOutputLedger = $this->ledgerByCode($return->company_id, '2201');

            $journal = $this->journals->createJournalEntry(
                $return->company_id,
                $return->return_date->toDateString(),
                JournalSourceType::SalesReturn,
                $return->id,
                $return->return_no,
                'Sales return '.$return->return_no,
            );

            $this->journals->addLine($journal, $salesLedger, $return->subtotal, 0, 'Sales return');

            if ((float) $return->vat_total > 0) {
                $this->journals->addLine($journal, $vatOutputLedger, $return->vat_total, 0, 'VAT output reversal');
            }

            $this->journals->addLine($journal, $customerLedger, 0, $return->total, 'Customer credit');
            $this->journals->post($journal);

            $this->syncStockMovements($return);

            $return->update(['journal_id' => $journal->id, 'status' => SalesReturnStatus::Posted]);

            return $return->refresh();
        });
    }

    public function recalculate(SalesReturn $return): void
    {
        $subtotal = 0.0;
        $vatTotal = 0.0;

        foreach ($return->items as $line) {
            $net = round((float) $line->qty * (float) $line->rate, 2);
            $vat = round($net * ((float) $line->vat_rate / 100), 2);
            $line->forceFill(['vat_amount' => $vat, 'line_total' => $net + $vat])->save();
            $subtotal += $net;
            $vatTotal += $vat;
        }

        $return->forceFill([
            'subtotal' => round($subtotal, 2),
            'vat_total' => round($vatTotal, 2),
            'total' => round($subtotal + $vatTotal, 2),
        ])->save();

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
