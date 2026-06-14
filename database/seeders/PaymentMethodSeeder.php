<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->firstOrFail();

        foreach (['Cash', 'Cheque', 'Bank Transfer', 'Other'] as $name) {
            PaymentMethod::query()->withoutGlobalScope('company')->updateOrCreate(
                ['company_id' => $company->id, 'name' => $name],
                ['is_enabled' => true],
            );
        }
    }
}
