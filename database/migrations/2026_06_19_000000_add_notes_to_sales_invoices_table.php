<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('sales_invoices', 'notes')) {
                $table->text('notes')->nullable()->after('payment_note');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table): void {
            if (Schema::hasColumn('sales_invoices', 'notes')) {
                $table->dropColumn('notes');
            }
        });
    }
};
