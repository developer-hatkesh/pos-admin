<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\JournalSourceType;
use App\Enums\PurchaseReturnStatus;
use App\Enums\StockMovementType;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Services\Accounting\Concerns\FindsLedgers;
use App\Services\Inventory\StockMovementService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PurchaseReturnPostingService
{
    use FindsLedgers;

    public function __construct(
        private readonly JournalService $journals,
        private readonly StockMovementService $stockMovements,
    ) {}

    public function post(PurchaseReturn $return): PurchaseReturn
    {
        if ($return->journal_id !== null || $return->status === PurchaseReturnStatus::Posted) {
            throw new RuntimeException('Purchase return is already posted.');
        }

        return DB::transaction(function () use ($return): PurchaseReturn {
            $return->loadMissing(['supplier.ledger', 'purchaseInvoice', 'items.productItem', 'items.purchaseInvoiceItem']);
            $this->validateReturnQuantities($return);
            $this->recalculate($return);

            $purchaseLedger = $this->ledgerByCode($return->company_id, '5000');
            $vatInputLedger = $this->ledgerByCode($return->company_id, '2202');
            $supplierLedger = $return->supplier?->ledger ?: $this->ledgerByCode($return->company_id, '2100');

            $journal = $this->journals->createJournalEntry(
                $return->company_id,
                $return->return_date->toDateString(),
                JournalSourceType::PurchaseReturn,
                $return->id,
                $return->return_no,
                'Purchase return '.$return->return_no,
            );

            $this->journals->addLine($journal, $supplierLedger, $return->total, 0, 'Supplier debit');
            $this->journals->addLine($journal, $purchaseLedger, 0, $return->subtotal, 'Purchase reversal');

            if ((float) $return->vat_total > 0) {
                $this->journals->addLine($journal, $vatInputLedger, 0, $return->vat_total, 'VAT input reversal');
            }

            $this->journals->post($journal);

            foreach ($return->items as $line) {
                if ($line->productItem?->stock_enabled) {
                    $this->stockMovements->create($line->productItem, StockMovementType::PurchaseReturn, $line->qty, $line->rate, $return->return_date->toDateString(), PurchaseReturn::class, $return->id);
                }
            }

            $return->update(['journal_id' => $journal->id, 'status' => PurchaseReturnStatus::Posted]);

            return $return->refresh();
        });
    }

    public function recalculate(PurchaseReturn $return): void
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
    }

    private function validateReturnQuantities(PurchaseReturn $return): void
    {
        foreach ($return->items as $line) {
            $source = $line->purchaseInvoiceItem;

            if (! $source) {
                throw new RuntimeException('A purchase return line is missing its source purchase item.');
            }

            $purchasedQty = (float) PurchaseInvoiceItem::query()
                ->where('id', $source->id)
                ->where('invoice_id', $return->purchase_invoice_id)
                ->sum('qty');
            $alreadyReturned = (float) PurchaseReturnItem::query()
                ->where('purchase_invoice_item_id', $source->id)
                ->where('purchase_return_id', '!=', $return->id)
                ->whereHas('purchaseReturn', fn ($query) => $query->where('status', PurchaseReturnStatus::Posted->value))
                ->sum('qty');
            $remaining = round($purchasedQty - $alreadyReturned, 3);

            if ((float) $line->qty > $remaining) {
                throw new RuntimeException('Return quantity for '.$line->description.' exceeds remaining purchased quantity.');
            }
        }
    }
}
