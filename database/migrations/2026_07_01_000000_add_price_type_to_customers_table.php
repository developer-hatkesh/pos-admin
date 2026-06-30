<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            if (Schema::hasColumn('customers', 'price_type')) {
                return;
            }

            $table->string('price_type', 20)->default('retail')->after('discount_percent')->index();
        });

        DB::table('customers')
            ->whereNull('price_type')
            ->orWhereNotIn('price_type', ['retail', 'wholesale'])
            ->update(['price_type' => 'retail']);
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            if (! Schema::hasColumn('customers', 'price_type')) {
                return;
            }

            $table->dropColumn('price_type');
        });
    }
};
