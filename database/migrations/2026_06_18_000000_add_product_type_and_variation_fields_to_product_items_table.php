<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('product_items', 'product_code')) {
                $table->string('product_code')->nullable()->after('company_id');
            }

            if (! Schema::hasColumn('product_items', 'product_type')) {
                $table->string('product_type')->default('single')->after('name')->index();
            }

            if (! Schema::hasColumn('product_items', 'parent_product_item_id')) {
                $table->foreignId('parent_product_item_id')->nullable()->after('product_type')->constrained('product_items')->nullOnDelete();
            }

            if (! Schema::hasColumn('product_items', 'variation_id')) {
                $table->foreignId('variation_id')->nullable()->after('parent_product_item_id')->constrained('variations')->nullOnDelete();
            }

            if (! Schema::hasColumn('product_items', 'variation_type_id')) {
                $table->foreignId('variation_type_id')->nullable()->after('variation_id')->constrained('variation_types')->nullOnDelete();
            }

            if (! Schema::hasColumn('product_items', 'sku')) {
                $table->string('sku')->nullable()->after('variation_type_id');
            }

            if (! Schema::hasColumn('product_items', 'tax_type')) {
                $table->string('tax_type')->default('exclusive')->after('vat_rate');
            }

            if (! Schema::hasColumn('product_items', 'stock_alert_qty')) {
                $table->decimal('stock_alert_qty', 15, 3)->nullable()->after('opening_stock');
            }

            if (! Schema::hasColumn('product_items', 'expiry_date')) {
                $table->date('expiry_date')->nullable()->after('stock_alert_qty');
            }

            if (! $this->indexExists('product_items', 'product_items_company_sku_unique')) {
                $table->unique(['company_id', 'sku'], 'product_items_company_sku_unique');
            }

            if (! $this->indexExists('product_items', 'product_items_company_product_code_unique')) {
                $table->unique(['company_id', 'product_code'], 'product_items_company_product_code_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_items', function (Blueprint $table): void {
            if ($this->indexExists('product_items', 'product_items_company_sku_unique')) {
                $table->dropUnique('product_items_company_sku_unique');
            }

            if ($this->indexExists('product_items', 'product_items_company_product_code_unique')) {
                $table->dropUnique('product_items_company_product_code_unique');
            }

            foreach (['expiry_date', 'stock_alert_qty', 'tax_type', 'sku'] as $column) {
                if (Schema::hasColumn('product_items', $column)) {
                    $table->dropColumn($column);
                }
            }

            foreach (['variation_type_id', 'variation_id', 'parent_product_item_id'] as $column) {
                if (Schema::hasColumn('product_items', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            if (Schema::hasColumn('product_items', 'product_type')) {
                $table->dropColumn('product_type');
            }

            if (Schema::hasColumn('product_items', 'product_code')) {
                $table->dropColumn('product_code');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $existingIndex): bool => ($existingIndex['name'] ?? null) === $index);
    }
};
