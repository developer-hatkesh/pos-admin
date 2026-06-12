<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PartyType;
use App\Enums\Status;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class PartyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'type' => fake()->randomElement(PartyType::cases()),
            'name' => fake()->company(),
            'email' => fake()->safeEmail(),
            'country' => 'UK',
            'status' => Status::Active,
        ];
    }
}
