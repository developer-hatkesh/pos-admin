<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Status;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'customer_code' => fake()->unique()->bothify('CUST###'),
            'name' => fake()->company(),
            'company_name' => fake()->company(),
            'contact_person' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'mobile_no' => fake()->phoneNumber(),
            'telephone_no' => fake()->phoneNumber(),
            'currency_id' => 'GBP',
            'discount_percent' => 0,
            'payment_terms_days' => 30,
            'credit_limit' => 0,
            'opening_balance' => 0,
            'country' => 'UK',
            'status' => Status::Active,
        ];
    }
}
