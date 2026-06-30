<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table): void {
            if (Schema::hasColumn('purchase_invoices', 'discount')) {
                return;
            }

            $table->decimal('discount', 15, 2)->default(0)->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchase_invoices', 'discount')) {
                return;
            }

            $table->dropColumn('discount');
        });
    }
};
