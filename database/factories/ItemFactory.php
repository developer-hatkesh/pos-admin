<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ItemUnit;
use App\Enums\Status;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'item_code' => fake()->unique()->bothify('SKU-####'),
            'name' => fake()->words(3, true),
            'unit' => ItemUnit::Bottle,
            'purchase_price' => fake()->randomFloat(2, 5, 30),
            'sale_price' => fake()->randomFloat(2, 10, 80),
            'vat_rate' => 20,
            'stock_enabled' => true,
            'status' => Status::Active,
        ];
    }
}
