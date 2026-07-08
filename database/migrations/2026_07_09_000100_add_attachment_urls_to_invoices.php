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
            if (! Schema::hasColumn('sales_invoices', 'attachment_url')) {
                $table->text('attachment_url')->nullable()->after('notes');
            }
        });

        Schema::table('purchase_invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchase_invoices', 'attachment_url')) {
                $table->text('attachment_url')->nullable()->after('journal_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table): void {
            if (Schema::hasColumn('purchase_invoices', 'attachment_url')) {
                $table->dropColumn('attachment_url');
            }
        });

        Schema::table('sales_invoices', function (Blueprint $table): void {
            if (Schema::hasColumn('sales_invoices', 'attachment_url')) {
                $table->dropColumn('attachment_url');
            }
        });
    }
};
