<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\StockMovementType;
use App\Models\ProductItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class StockMovementService
{
    public function create(ProductItem $item, StockMovementType $type, float|string $quantity, float|string $rate, string $movementDate, ?string $referenceType = null, ?int $referenceId = null): StockMovement
    {
        $movement = StockMovement::query()->create([
            'company_id' => $item->company_id,
            'product_item_id' => $item->id,
            'type' => $type,
            'quantity' => $quantity,
            'rate' => $rate,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'movement_date' => $movementDate,
            'created_at' => now(),
        ]);

        $delta = $type->increasesStock() ? (float) $quantity : -(float) $quantity;

        ProductItem::query()
            ->whereKey($item->id)
            ->update(['current_stock' => DB::raw('COALESCE(current_stock, 0) + '.number_format($delta, 3, '.', ''))]);

        return $movement;
    }

    public function deleteForReference(string $referenceType, int $referenceId): void
    {
        StockMovement::query()
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->get()
            ->each(function (StockMovement $movement): void {
                $type = $movement->type instanceof StockMovementType
                    ? $movement->type
                    : StockMovementType::tryFrom((string) $movement->type);

                if ($movement->product_item_id !== null && $type !== null) {
                    $delta = $type->increasesStock()
                        ? -(float) $movement->quantity
                        : (float) $movement->quantity;

                    ProductItem::query()
                        ->whereKey($movement->product_item_id)
                        ->update(['current_stock' => DB::raw('COALESCE(current_stock, 0) + '.number_format($delta, 3, '.', ''))]);
                }

                $movement->delete();
            });
    }
}
