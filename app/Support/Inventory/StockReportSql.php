<?php

declare(strict_types=1);

namespace App\Support\Inventory;

use App\Enums\StockMovementType;

final class StockReportSql
{
    public static function quotedTypes(bool $inward): string
    {
        return collect(StockMovementType::cases())
            ->filter(fn (StockMovementType $type): bool => $type->increasesStock() === $inward)
            ->map(fn (StockMovementType $type): string => "'".str_replace("'", "''", $type->value)."'")
            ->implode(', ');
    }

    public static function inwardTypes(): array
    {
        return collect(StockMovementType::cases())
            ->filter(fn (StockMovementType $type): bool => $type->increasesStock())
            ->map(fn (StockMovementType $type): string => $type->value)
            ->values()
            ->all();
    }

    public static function outwardTypes(): array
    {
        return collect(StockMovementType::cases())
            ->reject(fn (StockMovementType $type): bool => $type->increasesStock())
            ->map(fn (StockMovementType $type): string => $type->value)
            ->values()
            ->all();
    }

    public static function currentStockSql(string $productTable = 'product_items'): string
    {
        $inwardTypes = self::quotedTypes(true);

        return "(COALESCE({$productTable}.opening_stock, 0) + COALESCE((
            SELECT SUM(CASE
                WHEN stock_movements.type IN ({$inwardTypes}) THEN stock_movements.quantity
                ELSE -stock_movements.quantity
            END)
            FROM stock_movements
            WHERE stock_movements.product_item_id = {$productTable}.id
        ), 0))";
    }

    public static function movementQuantitySql(bool $inward, string $productTable = 'product_items'): string
    {
        $types = self::quotedTypes($inward);

        return "COALESCE((
            SELECT SUM(stock_movements.quantity)
            FROM stock_movements
            WHERE stock_movements.product_item_id = {$productTable}.id
                AND stock_movements.type IN ({$types})
        ), 0)";
    }
}
