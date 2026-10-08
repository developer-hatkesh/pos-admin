<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_lines', function (Blueprint $table): void {
            $table->foreignId('customer_id')->nullable()->after('ledger_id')->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->after('supplier_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('journal_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('bank_account_id');
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropConstrainedForeignId('customer_id');
        });
    }
};
