<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['sales_invoices', 'sales_returns', 'purchase_invoices', 'purchase_returns'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->decimal('shipping', 15, 2)->default(0)->after('vat_total');
            });
        }
    }

    public function down(): void
    {
        foreach (['sales_invoices', 'sales_returns', 'purchase_invoices', 'purchase_returns'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('shipping');
            });
        }
    }
};
