<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\StockMovementType;
use App\Models\Item;
use App\Models\StockMovement;

class StockMovementService
{
    public function create(Item $item, StockMovementType $type, float|string $quantity, float|string $rate, string $movementDate, ?string $referenceType = null, ?int $referenceId = null): StockMovement
    {
        return StockMovement::query()->create([
            'company_id' => $item->company_id,
            'item_id' => $item->id,
            'type' => $type,
            'quantity' => $quantity,
            'rate' => $rate,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'movement_date' => $movementDate,
            'created_at' => now(),
        ]);
    }
}
