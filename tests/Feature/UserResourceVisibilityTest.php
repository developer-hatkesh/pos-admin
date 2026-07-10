<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Status;
use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserResourceVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_user_listing_hides_super_admin_users(): void
    {
        $company = Company::factory()->create();

        $normalUser = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Viewer,
            'status' => Status::Active,
        ]);

        $visibleUser = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Viewer,
            'status' => Status::Active,
        ]);

        $legacyAdmin = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'status' => Status::Active,
        ]);

        $legacySuperAdmin = User::factory()->create([
            'company_id' => $company->id,
            'role' => config('filament-shield.super_admin.name', 'super_admin'),
            'status' => Status::Active,
        ]);

        $shieldSuperAdmin = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Viewer,
            'status' => Status::Active,
        ]);

        $this->assignSuperAdminRole($shieldSuperAdmin, $company);

        $this->actingAs($normalUser);

        $ids = UserResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($normalUser->id, $ids);
        $this->assertContains($visibleUser->id, $ids);
        $this->assertContains($legacyAdmin->id, $ids);
        $this->assertNotContains($legacySuperAdmin->id, $ids);
        $this->assertNotContains($shieldSuperAdmin->id, $ids);
    }

    public function test_super_admin_listing_can_see_super_admin_users(): void
    {
        $company = Company::factory()->create();

        $superAdmin = User::factory()->create([
            'company_id' => $company->id,
            'role' => config('filament-shield.super_admin.name', 'super_admin'),
            'status' => Status::Active,
        ]);

        $otherSuperAdmin = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Viewer,
            'status' => Status::Active,
        ]);

        $this->assignSuperAdminRole($otherSuperAdmin, $company);

        $this->actingAs($superAdmin);

        $ids = UserResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($superAdmin->id, $ids);
        $this->assertContains($otherSuperAdmin->id, $ids);
    }

    public function test_admin_listing_cannot_see_super_admin_users(): void
    {
        $company = Company::factory()->create();

        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'status' => Status::Active,
        ]);

        $superAdmin = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::SuperAdmin,
            'status' => Status::Active,
        ]);

        $this->actingAs($admin);

        $ids = UserResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($admin->id, $ids);
        $this->assertNotContains($superAdmin->id, $ids);
    }

    private function assignSuperAdminRole(User $user, Company $company): void
    {
        $role = Role::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => config('filament-shield.super_admin.name', 'super_admin'),
            'guard_name' => 'web',
        ]);

        DB::table(config('permission.table_names.model_has_roles', 'model_has_roles'))->insert([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'model_type' => $user->getMorphClass(),
            'model_id' => $user->getKey(),
        ]);
    }
}
