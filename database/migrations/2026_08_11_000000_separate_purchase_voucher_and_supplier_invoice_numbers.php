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
        Schema::table('purchase_invoices', function (Blueprint $table): void {
            $table->string('voucher_no')->nullable()->after('invoice_no');
            $table->string('supplier_invoice_no')->nullable()->after('voucher_no');
        });

        DB::table('purchase_invoices')->update(['voucher_no' => DB::raw('invoice_no')]);

        Schema::table('purchase_invoices', function (Blueprint $table): void {
            $table->unique(['company_id', 'voucher_no']);
            $table->index(['company_id', 'supplier_id', 'supplier_invoice_no'], 'purchase_supplier_invoice_lookup');
        });

        Schema::table('purchase_invoice_items', function (Blueprint $table): void {
            $table->string('description')->nullable()->after('product_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoice_items', function (Blueprint $table): void {
            $table->dropColumn('description');
        });

        Schema::table('purchase_invoices', function (Blueprint $table): void {
            $table->dropIndex('purchase_supplier_invoice_lookup');
            $table->dropUnique(['company_id', 'voucher_no']);
            $table->dropColumn(['voucher_no', 'supplier_invoice_no']);
        });
    }
};
