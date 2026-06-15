<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Status;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'supplier_code' => fake()->unique()->bothify('SUP###'),
            'name' => fake()->company(),
            'company_name' => fake()->company(),
            'contact_person' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'mobile_no' => fake()->phoneNumber(),
            'telephone_no' => fake()->phoneNumber(),
            'currency_id' => 'GBP',
            'payment_terms' => 30,
            'opening_balance' => 0,
            'country' => 'UK',
            'status' => Status::Active,
        ];
    }
}
