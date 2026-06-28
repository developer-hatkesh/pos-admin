<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table): void {
            $table->string('receipt_voucher_type')->default('customer')->after('payment_voucher_type')->index();
        });

        Schema::table('voucher_allocations', function (Blueprint $table): void {
            $table->foreignId('purchase_return_id')->nullable()->after('sales_return_id')->constrained('purchase_returns')->cascadeOnDelete();
            $table->foreignId('income_id')->nullable()->after('purchase_return_id')->constrained('incomes')->cascadeOnDelete();
            $table->index(['purchase_return_id']);
            $table->index(['income_id']);
        });
    }

    public function down(): void
    {
        Schema::table('voucher_allocations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('income_id');
            $table->dropConstrainedForeignId('purchase_return_id');
        });

        Schema::table('vouchers', function (Blueprint $table): void {
            $table->dropColumn('receipt_voucher_type');
        });
    }
};
