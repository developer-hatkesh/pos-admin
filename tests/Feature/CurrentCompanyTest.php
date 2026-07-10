<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentCompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_switcher_options_only_include_companies_assigned_to_user(): void
    {
        $primaryCompany = Company::factory()->create(['name' => 'Primary Company']);
        $assignedCompany = Company::factory()->create(['name' => 'Assigned Company']);
        $unassignedCompany = Company::factory()->create(['name' => 'Unassigned Company']);

        $user = User::factory()->create([
            'company_id' => $primaryCompany->id,
            'role' => UserRole::Admin,
        ]);
        $user->companies()->attach($assignedCompany);

        $this->actingAs($user);

        $companyIds = app(CurrentCompany::class)->companiesFor($user)->pluck('id')->all();

        $this->assertContains($primaryCompany->id, $companyIds);
        $this->assertContains($assignedCompany->id, $companyIds);
        $this->assertNotContains($unassignedCompany->id, $companyIds);
    }

    public function test_unassigned_company_cannot_remain_selected_in_session(): void
    {
        $assignedCompany = Company::factory()->create();
        $unassignedCompany = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $assignedCompany->id,
            'role' => UserRole::Admin,
        ]);

        $this->actingAs($user)
            ->withSession([CurrentCompany::SESSION_KEY => $unassignedCompany->id]);

        $this->assertSame($assignedCompany->id, app(CurrentCompany::class)->id());
        $this->assertFalse(app(CurrentCompany::class)->canAccessCompany($unassignedCompany->id, $user));
    }
}
