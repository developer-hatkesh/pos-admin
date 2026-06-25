<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('variation_types', function (Blueprint $table): void {
            if (! Schema::hasColumn('variation_types', 'sku_suffix')) {
                $table->string('sku_suffix')->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('variation_types', function (Blueprint $table): void {
            if (Schema::hasColumn('variation_types', 'sku_suffix')) {
                $table->dropColumn('sku_suffix');
            }
        });
    }
};
