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
        Schema::table('app_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('app_settings', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            }
        });

        DB::table('app_settings')->whereNull('company_id')->update(['company_id' => 1]);

        Schema::table('app_settings', function (Blueprint $table): void {
            $table->dropUnique(['key']);
            $table->unique(['company_id', 'key']);
        });

        Schema::table('companies', function (Blueprint $table): void {
            if (Schema::hasColumn('companies', 'currency')) {
                $table->dropColumn('currency');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            if (! Schema::hasColumn('companies', 'currency')) {
                $table->string('currency', 3)->default('GBP')->after('business_phone_number');
            }
        });

        Schema::table('app_settings', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'key']);
            $table->unique('key');
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
