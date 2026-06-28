<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->foreignId('payment_bank_account_id')->nullable()->after('supplier_id')->constrained('bank_accounts')->nullOnDelete();
            $table->date('payment_date')->nullable()->after('payment_bank_account_id')->index();
            $table->foreignId('payment_voucher_id')->nullable()->after('payment_date')->constrained('vouchers')->nullOnDelete();

            $table->index(['company_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payment_voucher_id');
            $table->dropIndex(['company_id', 'payment_date']);
            $table->dropIndex(['payment_date']);
            $table->dropColumn('payment_date');
            $table->dropConstrainedForeignId('payment_bank_account_id');
        });
    }
};
