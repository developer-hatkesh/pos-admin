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
            [
                'name' => 'Default Perfume Shop',
                'email' => 'admin@seo.com'
            ],
            [
                'contact_person_name' => 'Admin',
                'phone' => '00000000000',
                'legal_business_name' => 'Default Perfume Shop',
                'business_phone_number' => '00000000000',
                'address' => 'Default registered address',
                'city' => 'London',
                'postcode' => 'SW1A 1AA',
                'country' => 'UK',
                'number_of_employees' => 'SOLO',
                'currency' => 'GBP',
                'financial_year_start' => now()->startOfYear()->toDateString(),
                'financial_year_end' => now()->endOfYear()->toDateString(),
            ],
        );
    }
}
