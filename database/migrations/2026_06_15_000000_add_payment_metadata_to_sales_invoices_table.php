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
            if (! Schema::hasColumn('sales_invoices', 'payment_method_id')) {
                $table->foreignId('payment_method_id')
                    ->nullable()
                    ->after('journal_id')
                    ->constrained('payment_methods')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('sales_invoices', 'payment_note')) {
                $table->text('payment_note')->nullable()->after('payment_method_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table): void {
            if (Schema::hasColumn('sales_invoices', 'payment_method_id')) {
                $table->dropConstrainedForeignId('payment_method_id');
            }

            if (Schema::hasColumn('sales_invoices', 'payment_note')) {
                $table->dropColumn('payment_note');
            }
        });
    }
};
