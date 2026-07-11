<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Companies\CompanyResource;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyResourceVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_listing_only_contains_companies_assigned_to_the_user(): void
    {
        $defaultCompany = Company::factory()->create();
        $assignedCompany = Company::factory()->create();
        $unassignedCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $defaultCompany->id]);
        $user->companies()->attach($assignedCompany);

        $this->actingAs($user);

        $companyIds = CompanyResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($defaultCompany->id, $companyIds);
        $this->assertContains($assignedCompany->id, $companyIds);
        $this->assertNotContains($unassignedCompany->id, $companyIds);
    }
}
