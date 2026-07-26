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
        if (DB::getDriverName() === 'mysql') {
            foreach (['suppliers', 'purchase_invoices'] as $table) {
                $engine = DB::selectOne('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table])?->ENGINE;

                if ($engine !== null && $engine !== 'InnoDB') {
                    DB::statement("ALTER TABLE `{$table}` ENGINE = InnoDB");
                }
            }
        }

        Schema::table('journal_vouchers', function (Blueprint $table): void {
            $table->foreignId('purchase_return_id')->nullable()->after('sales_return_id')->constrained('purchase_returns')->restrictOnDelete();
            $table->foreignId('sales_invoice_id')->nullable()->after('purchase_return_id')->constrained('sales_invoices')->restrictOnDelete();
            $table->foreignId('purchase_invoice_id')->nullable()->after('sales_invoice_id')->constrained('purchase_invoices')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->after('purchase_invoice_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->after('customer_id')->constrained('suppliers')->restrictOnDelete();
            $table->unique(['company_id', 'purchase_return_id'], 'jv_company_purchase_return_unique');
            $table->unique(['company_id', 'sales_invoice_id'], 'jv_company_sales_invoice_unique');
            $table->unique(['company_id', 'purchase_invoice_id'], 'jv_company_purchase_invoice_unique');
        });

        Schema::table('journal_voucher_allocations', function (Blueprint $table): void {
            $table->foreignId('sales_invoice_id')->nullable()->change();
            $table->foreignId('purchase_invoice_id')->nullable()->after('sales_invoice_id')->constrained('purchase_invoices')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('journal_voucher_allocations', 'purchase_invoice_id')) {
            Schema::table('journal_voucher_allocations', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('purchase_invoice_id');
            });
        }

        foreach (['jv_company_purchase_return_unique', 'jv_company_sales_invoice_unique', 'jv_company_purchase_invoice_unique'] as $index) {
            if (Schema::hasIndex('journal_vouchers', $index)) {
                Schema::table('journal_vouchers', fn (Blueprint $table) => $table->dropUnique($index));
            }
        }

        foreach (['purchase_return_id', 'sales_invoice_id', 'purchase_invoice_id', 'customer_id', 'supplier_id'] as $column) {
            if (Schema::hasColumn('journal_vouchers', $column)) {
                Schema::table('journal_vouchers', fn (Blueprint $table) => $table->dropConstrainedForeignId($column));
            }
        }
    }
};
