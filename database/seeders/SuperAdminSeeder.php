<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Status;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        if (Company::query()->doesntExist()) {
            $this->call(DefaultCompanySeeder::class);
        }

        $user = User::query()->updateOrCreate([
            'email' => 'super@pos.com',
        ], [
            'name' => 'Super Admin',
            'company_id' => Company::query()->orderBy('id')->value('id'),
            'password' => 'New@1234',
            'role' => UserRole::Viewer,
            'status' => Status::Active,
        ]);

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->pluck('name');

        $previousTeamId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;

        Company::query()->orderBy('id')->each(function (Company $company) use ($user, $permissions): void {
            if (! $user->companies()->whereKey($company->id)->exists()) {
                $user->companies()->attach($company->id);
            }

            if (function_exists('setPermissionsTeamId')) {
                setPermissionsTeamId($company->id);
            }

            $role = Role::query()->withoutGlobalScopes()->firstOrCreate([
                'name' => config('filament-shield.super_admin.name', 'super_admin'),
                'guard_name' => 'web',
                'company_id' => $company->id,
            ]);

            if ($permissions->isNotEmpty()) {
                $role->syncPermissions($permissions);
            }

            $user->assignRole($role);
        });

        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId($previousTeamId);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
