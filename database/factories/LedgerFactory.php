<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LedgerType;
use App\Enums\Status;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class LedgerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->words(2, true),
            'nominal_code' => fake()->unique()->numerify('####'),
            'type' => fake()->randomElement(LedgerType::cases()),
            'status' => Status::Active,
        ];
    }
}
