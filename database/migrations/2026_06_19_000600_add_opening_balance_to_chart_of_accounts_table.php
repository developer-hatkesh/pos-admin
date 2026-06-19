<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('chart_of_accounts', 'opening_balance')) {
                $table->decimal('opening_balance', 15, 2)->default(0)->after('normal_balance_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table): void {
            if (Schema::hasColumn('chart_of_accounts', 'opening_balance')) {
                $table->dropColumn('opening_balance');
            }
        });
    }
};
