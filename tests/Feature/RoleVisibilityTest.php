<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_role_is_hidden_from_non_super_admin_users(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
        ]);

        $this->createRole($company, 'super_admin');
        $visibleRole = $this->createRole($company, 'company_admin');

        $this->actingAs($user);

        $this->assertSame([$visibleRole->id], Role::query()->pluck('id')->all());
    }

    public function test_super_admin_role_is_visible_to_platform_super_admin_users(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'super_admin',
        ]);

        $superAdminRole = $this->createRole($company, 'super_admin');

        $this->actingAs($user);

        $this->assertTrue(Role::query()->whereKey($superAdminRole)->exists());
    }

    public function test_team_relationship_only_resolves_the_selected_company(): void
    {
        $defaultCompany = Company::factory()->create();
        $selectedCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $defaultCompany->id]);
        $user->companies()->attach([$defaultCompany->id, $selectedCompany->id]);

        $selectedRole = $this->createRole($selectedCompany, 'company_admin');
        $otherRole = $this->createRole($defaultCompany, 'viewer');

        $this->actingAs($user);
        request()->setLaravelSession(app('session')->driver());
        session()->put(CurrentCompany::SESSION_KEY, $selectedCompany->id);

        $this->assertTrue($selectedRole->team()->whereKey($selectedCompany)->exists());
        $this->assertFalse($otherRole->team()->exists());
    }

    private function createRole(Company $company, string $name): Role
    {
        return Role::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => $name,
            'guard_name' => 'web',
        ]);
    }
}
