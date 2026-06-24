<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'contact_person_name' => fake()->name(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'legal_business_name' => fake()->company(),
            'business_phone_number' => fake()->phoneNumber(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'postcode' => fake()->postcode(),
            'country' => 'UK',
            'number_of_employees' => 'SOLO',
            'financial_year_start' => now()->startOfYear()->toDateString(),
            'financial_year_end' => now()->endOfYear()->toDateString(),
        ];
    }
}
