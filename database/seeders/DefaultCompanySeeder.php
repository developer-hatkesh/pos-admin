<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class DefaultCompanySeeder extends Seeder
{
    public function run(): void
    {
        Company::query()->updateOrCreate(
            ['name' => 'Default Perfume Shop'],
            [
                'email' => 'admin@seo.com',
                'country' => 'UK',
                'currency' => 'GBP',
            ],
        );
    }
}
