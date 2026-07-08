<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class ShieldRoleSeeder extends Seeder
{
    public function run(): void
    {
        $guard = 'web';
        $permissions = Permission::query()->where('guard_name', $guard)->pluck('name');

        Company::query()->orderBy('id')->each(function (Company $company) use ($guard, $permissions): void {
            setPermissionsTeamId($company->id);

            $superAdmin = $this->role('super_admin', $guard, $company->id);
            $companyAdmin = $this->role('company_admin', $guard, $company->id);
            $accountant = $this->role('accountant', $guard, $company->id);
            $sales = $this->role('sales', $guard, $company->id);
            $inventory = $this->role('inventory', $guard, $company->id);
            $viewer = $this->role('viewer', $guard, $company->id);

            $superAdmin->syncPermissions($permissions);
            $companyAdmin->syncPermissions($this->companyAdminPermissions($permissions));
            $accountant->syncPermissions($this->accountantPermissions($permissions));
            $sales->syncPermissions($this->salesPermissions($permissions));
            $inventory->syncPermissions($this->inventoryPermissions($permissions));
            $viewer->syncPermissions($this->viewerPermissions($permissions));

            $this->migrateLegacyUsers($company);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function role(string $name, string $guard, int $companyId): Role
    {
        return Role::query()->withoutGlobalScopes()->firstOrCreate([
            'name' => $name,
            'guard_name' => $guard,
            'company_id' => $companyId,
        ]);
    }

    private function migrateLegacyUsers(Company $company): void
    {
        User::query()
            ->where(function ($query) use ($company): void {
                $query
                    ->where('company_id', $company->id)
                    ->orWhereHas('companies', fn ($query) => $query->whereKey($company->id));
            })
            ->each(function (User $user) use ($company): void {
                if (! $user->companies()->whereKey($company->id)->exists()) {
                    $user->companies()->attach($company->id);
                }

                setPermissionsTeamId($company->id);

                $role = match (UserRole::tryFrom((string) $user->legacyRoleValue())) {
                    UserRole::Admin => 'super_admin',
                    UserRole::Accountant => 'accountant',
                    UserRole::Sales => 'sales',
                    default => 'viewer',
                };

                $user->assignRole($role);
            });
    }

    private function companyAdminPermissions($permissions): array
    {
        return $permissions
            ->reject(fn (string $permission): bool => str_ends_with($permission, ':Company'))
            ->reject(fn (string $permission): bool => in_array($permission, ['ForceDelete:Role', 'ForceDeleteAny:Role'], true))
            ->values()
            ->all();
    }

    private function accountantPermissions($permissions): array
    {
        return $permissions
            ->reject(fn (string $permission): bool => str_ends_with($permission, ':User') || str_ends_with($permission, ':Role') || str_ends_with($permission, ':Company'))
            ->values()
            ->all();
    }

    private function salesPermissions($permissions): array
    {
        return $permissions
            ->filter(fn (string $permission): bool => str_contains($permission, ':Sales')
                || str_contains($permission, ':Estimate')
                || str_contains($permission, ':ItemSalesReport')
                || str_contains($permission, ':Customer')
                || str_contains($permission, ':ProductItem')
                || str_contains($permission, ':PaymentMethod')
                || $permission === 'view_PosSales')
                ->values()
                ->all();
    }

    private function inventoryPermissions($permissions): array
    {
        return $permissions
            ->filter(fn (string $permission): bool => str_contains($permission, ':ProductItem')
                || str_contains($permission, ':Item')
                || str_contains($permission, ':Stock')
                || str_contains($permission, ':Brand')
                || str_contains($permission, ':Category')
                || str_contains($permission, ':Variation'))
            ->values()
            ->all();
    }

    private function viewerPermissions($permissions): array
    {
        return $permissions
            ->filter(fn (string $permission): bool => str_starts_with($permission, 'View') || str_starts_with($permission, 'view_'))
            ->values()
            ->all();
    }
}
