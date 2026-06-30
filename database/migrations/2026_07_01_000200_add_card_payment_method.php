<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('companies') || ! Schema::hasTable('payment_methods')) {
            return;
        }

        DB::table('companies')
            ->orderBy('id')
            ->each(function (object $company): void {
                DB::table('payment_methods')->updateOrInsert(
                    ['company_id' => $company->id, 'name' => 'Card Payment'],
                    [
                        'is_enabled' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            });
    }

    public function down(): void
    {
        //
    }
};
