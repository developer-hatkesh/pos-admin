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
            $table->string('payment_voucher_type')->default('purchase')->after('voucher_type')->index();
        });

        Schema::table('voucher_allocations', function (Blueprint $table): void {
            $table->foreignId('sales_return_id')->nullable()->after('expense_id')->constrained('sales_returns')->cascadeOnDelete();
            $table->index(['sales_return_id']);
        });
    }

    public function down(): void
    {
        Schema::table('voucher_allocations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sales_return_id');
        });

        Schema::table('vouchers', function (Blueprint $table): void {
            $table->dropColumn('payment_voucher_type');
        });
    }
};
