<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Status;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            DefaultCompanySeeder::class,
            AccountClassCategorySeeder::class,
            ChartOfAccountMasterSeeder::class,
            ChartOfAccountsSeeder::class,
            PaymentMethodSeeder::class,
            TaxRateSeeder::class,
        ]);

        $company = Company::query()->first();

        User::query()->updateOrCreate([
            'email' => 'admin@example.com',
        ], [
            'name' => 'Administrator',
            'company_id' => $company?->id,
            'password' => 'password',
            'role' => UserRole::Admin,
            'status' => Status::Active,
        ]);
    }
}
