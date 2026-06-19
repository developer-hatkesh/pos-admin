<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TaxRateSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('tax_rates')->upsert([
            ['id' => 1, 'name' => 'Standard', 'rate' => 20.00, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Reduced', 'rate' => 5.00, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Zero', 'rate' => 0.00, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'name' => 'Exempt', 'rate' => 0.00, 'created_at' => now(), 'updated_at' => now()],
        ], ['id'], ['name', 'rate', 'updated_at']);
    }
}
