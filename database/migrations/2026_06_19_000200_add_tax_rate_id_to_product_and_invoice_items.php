<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('product_items', 'tax_rate_id')) {
                $table->foreignId('tax_rate_id')->nullable()->after('vat_rate')->constrained('tax_rates')->nullOnDelete();
            }
        });

        Schema::table('sales_invoice_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('sales_invoice_items', 'tax_rate_id')) {
                $table->foreignId('tax_rate_id')->nullable()->after('vat_rate')->constrained('tax_rates')->nullOnDelete();
            }
        });

        Schema::table('purchase_invoice_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchase_invoice_items', 'tax_rate_id')) {
                $table->foreignId('tax_rate_id')->nullable()->after('vat_rate')->constrained('tax_rates')->nullOnDelete();
            }
        });

        $this->backfillTaxRateIds('product_items');
        $this->backfillTaxRateIds('sales_invoice_items');
        $this->backfillTaxRateIds('purchase_invoice_items');
    }

    public function down(): void
    {
        Schema::table('purchase_invoice_items', function (Blueprint $table): void {
            if (Schema::hasColumn('purchase_invoice_items', 'tax_rate_id')) {
                $table->dropConstrainedForeignId('tax_rate_id');
            }
        });

        Schema::table('sales_invoice_items', function (Blueprint $table): void {
            if (Schema::hasColumn('sales_invoice_items', 'tax_rate_id')) {
                $table->dropConstrainedForeignId('tax_rate_id');
            }
        });

        Schema::table('product_items', function (Blueprint $table): void {
            if (Schema::hasColumn('product_items', 'tax_rate_id')) {
                $table->dropConstrainedForeignId('tax_rate_id');
            }
        });
    }

    private function backfillTaxRateIds(string $table): void
    {
        foreach (DB::table('tax_rates')->get(['id', 'rate']) as $taxRate) {
            DB::table($table)
                ->whereNull('tax_rate_id')
                ->where('vat_rate', (float) $taxRate->rate)
                ->update(['tax_rate_id' => $taxRate->id]);
        }
    }
};
