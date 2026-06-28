<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('product_items', 'current_stock')) {
                $table->decimal('current_stock', 15, 3)->default(0)->after('opening_stock');
            }

            $this->addIndexIfMissing($table, 'product_items_pos_barcode_idx', ['company_id', 'barcode']);
            $this->addIndexIfMissing($table, 'product_items_pos_code_idx', ['company_id', 'item_code']);
            $this->addIndexIfMissing($table, 'product_items_pos_category_idx', ['company_id', 'category_id', 'status']);
            $this->addIndexIfMissing($table, 'product_items_pos_brand_idx', ['company_id', 'brand_id', 'status']);
            $this->addIndexIfMissing($table, 'product_items_pos_variation_idx', ['company_id', 'variation_type_id', 'status']);
        });

        if (Schema::hasTable('stock_movements')) {
            Schema::table('stock_movements', function (Blueprint $table): void {
                $this->addIndexIfMissing($table, 'stock_movements_product_type_idx', ['product_item_id', 'type']);
                $this->addIndexIfMissing($table, 'stock_movements_company_product_type_idx', ['company_id', 'product_item_id', 'type']);
            });
        }

        $this->backfillCurrentStock();
    }

    public function down(): void
    {
        Schema::table('product_items', function (Blueprint $table): void {
            foreach ([
                'product_items_pos_variation_idx',
                'product_items_pos_brand_idx',
                'product_items_pos_category_idx',
                'product_items_pos_code_idx',
                'product_items_pos_barcode_idx',
            ] as $index) {
                $this->dropIndexIfExists($table, $index);
            }

            if (Schema::hasColumn('product_items', 'current_stock')) {
                $table->dropColumn('current_stock');
            }
        });

        if (Schema::hasTable('stock_movements')) {
            Schema::table('stock_movements', function (Blueprint $table): void {
                $this->dropIndexIfExists($table, 'stock_movements_company_product_type_idx');
                $this->dropIndexIfExists($table, 'stock_movements_product_type_idx');
            });
        }
    }

    private function backfillCurrentStock(): void
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

    private function addIndexIfMissing(Blueprint $table, string $indexName, array $columns): void
    {
        if (! $this->indexExists($table->getTable(), $indexName)) {
            $table->index($columns, $indexName);
        }
    }

    private function dropIndexIfExists(Blueprint $table, string $indexName): void
    {
        if ($this->indexExists($table->getTable(), $indexName)) {
            $table->dropIndex($indexName);
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $existingIndex): bool => ($existingIndex['name'] ?? null) === $index);
    }
};
