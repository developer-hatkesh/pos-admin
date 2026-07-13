<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique('customers_customer_code_unique');
            $table->unique(['company_id', 'customer_code']);
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropUnique('suppliers_supplier_code_unique');
            $table->unique(['company_id', 'supplier_code']);
        });

        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->unique(['company_id', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'reference']);
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'supplier_code']);
            $table->unique('supplier_code');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'customer_code']);
            $table->unique('customer_code');
        });
    }
};
