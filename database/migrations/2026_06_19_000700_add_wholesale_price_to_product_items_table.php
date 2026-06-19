<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_items', function (Blueprint $table): void {
            if (Schema::hasColumn('product_items', 'wholesale_price')) {
                return;
            }

            $table->decimal('wholesale_price', 15, 2)->default(0)->after('sale_price');
        });
    }

    public function down(): void
    {
        Schema::table('product_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('product_items', 'wholesale_price')) {
                return;
            }

            $table->dropColumn('wholesale_price');
        });
    }
};
