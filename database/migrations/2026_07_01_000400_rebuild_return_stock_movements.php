<?php

declare(strict_types=1);

use App\Enums\SalesReturnStatus;
use App\Enums\PurchaseReturnStatus;
use App\Enums\StockMovementType;
use App\Models\PurchaseReturn;
use App\Models\SalesReturn;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_movements') || ! Schema::hasTable('product_items')) {
            return;
        }

        DB::table('stock_movements')
            ->whereIn('reference_type', [SalesReturn::class, PurchaseReturn::class])
            ->delete();

        $this->insertSalesReturnMovements();
        $this->insertPurchaseReturnMovements();
        $this->rebuildCurrentStock();
    }

    public function down(): void
    {
        if (! Schema::hasTable('stock_movements') || ! Schema::hasTable('product_items')) {
            return;
        }

        DB::table('stock_movements')
            ->whereIn('reference_type', [SalesReturn::class, PurchaseReturn::class])
            ->delete();

        $this->rebuildCurrentStock();
    }

    private function insertSalesReturnMovements(): void
    {
        if (! Schema::hasTable('sales_returns') || ! Schema::hasTable('sales_return_items')) {
            return;
        }

        DB::table('stock_movements')->insertUsing(
            ['company_id', 'product_item_id', 'type', 'quantity', 'rate', 'reference_type', 'reference_id', 'movement_date', 'created_at'],
            DB::table('sales_return_items')
                ->join('sales_returns', 'sales_returns.id', '=', 'sales_return_items.sales_return_id')
                ->join('product_items', 'product_items.id', '=', 'sales_return_items.product_item_id')
                ->where('sales_returns.status', SalesReturnStatus::Posted->value)
                ->where('product_items.stock_enabled', true)
                ->where('product_items.product_type', '!=', 'service')
                ->select([
                    'sales_returns.company_id',
                    'sales_return_items.product_item_id',
                    DB::raw("'".StockMovementType::SalesReturn->value."'"),
                    'sales_return_items.qty',
                    'sales_return_items.rate',
                    DB::raw("'".str_replace("'", "''", SalesReturn::class)."'"),
                    'sales_returns.id',
                    'sales_returns.return_date',
                    DB::raw('CURRENT_TIMESTAMP'),
                ])
        );
    }

    private function insertPurchaseReturnMovements(): void
    {
        if (! Schema::hasTable('purchase_returns') || ! Schema::hasTable('purchase_return_items')) {
            return;
        }

        DB::table('stock_movements')->insertUsing(
            ['company_id', 'product_item_id', 'type', 'quantity', 'rate', 'reference_type', 'reference_id', 'movement_date', 'created_at'],
            DB::table('purchase_return_items')
                ->join('purchase_returns', 'purchase_returns.id', '=', 'purchase_return_items.purchase_return_id')
                ->join('product_items', 'product_items.id', '=', 'purchase_return_items.product_item_id')
                ->where('purchase_returns.status', PurchaseReturnStatus::Posted->value)
                ->where('product_items.stock_enabled', true)
                ->where('product_items.product_type', '!=', 'service')
                ->select([
                    'purchase_returns.company_id',
                    'purchase_return_items.product_item_id',
                    DB::raw("'".StockMovementType::PurchaseReturn->value."'"),
                    'purchase_return_items.qty',
                    'purchase_return_items.rate',
                    DB::raw("'".str_replace("'", "''", PurchaseReturn::class)."'"),
                    'purchase_returns.id',
                    'purchase_returns.return_date',
                    DB::raw('CURRENT_TIMESTAMP'),
                ])
        );
    }

    private function rebuildCurrentStock(): void
    {
        if (! Schema::hasColumn('product_items', 'current_stock')) {
            return;
        }

        $inwardTypes = collect(StockMovementType::cases())
            ->filter(fn (StockMovementType $type): bool => $type->increasesStock())
            ->map(fn (StockMovementType $type): string => "'".str_replace("'", "''", $type->value)."'")
            ->implode(', ');

        DB::statement("
            UPDATE product_items
            SET current_stock = CASE
                WHEN stock_enabled = 0 OR product_type = 'service' THEN 0
                ELSE COALESCE(opening_stock, 0) + COALESCE((
                    SELECT SUM(CASE
                        WHEN stock_movements.type IN ({$inwardTypes}) THEN stock_movements.quantity
                        ELSE -stock_movements.quantity
                    END)
                    FROM stock_movements
                    WHERE stock_movements.product_item_id = product_items.id
                ), 0)
            END
        ");
    }
};
