<?php

declare(strict_types=1);

namespace App\Filament\Resources\Companies\Pages;

use App\Enums\Status;
use App\Enums\UserRole;
use App\Filament\Resources\Companies\CompanyResource;
use App\Models\Role;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class CreateCompany extends CreateRecord
{
    protected static string $resource = CompanyResource::class;

    /**
     * @var array{name?: string|null, email?: string|null, password?: string|null}
     */
    private array $companyAdminData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->companyAdminData = [
            'name' => $data['company_admin_name'] ?? null,
            'email' => $data['company_admin_email'] ?? null,
            'password' => $data['company_admin_password'] ?? null,
        ];

        unset($data['company_admin_name'], $data['company_admin_email'], $data['company_admin_password']);

        return $data;
    }

    protected function afterCreate(): void
    {
        if (auth()->user()?->hasSuperAdminRole() !== true) {
            return;
        }

        if (blank($this->companyAdminData['name'] ?? null) || blank($this->companyAdminData['email'] ?? null) || blank($this->companyAdminData['password'] ?? null)) {
            return;
        }

        $user = User::query()->create([
            'name' => $this->companyAdminData['name'],
            'email' => $this->companyAdminData['email'],
            'password' => $this->companyAdminData['password'],
            'company_id' => $this->record->id,
            'role' => UserRole::Admin,
            'status' => Status::Active,
        ]);

        $user->companies()->syncWithoutDetaching([$this->record->id]);

        $this->assignCompanyAdminRole($user);
    }

    private function assignCompanyAdminRole(User $user): void
    {
        $previousTeamId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;

        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId($this->record->id);
        }

        $role = Role::query()->withoutGlobalScopes()->firstOrCreate([
            'name' => 'company_admin',
            'guard_name' => 'web',
            'company_id' => $this->record->id,
        ]);

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->pluck('name')
            ->reject(fn (string $permission): bool => str_ends_with($permission, ':Company'))
            ->reject(fn (string $permission): bool => in_array($permission, ['ForceDelete:Role', 'ForceDeleteAny:Role'], true))
            ->values();

        $role->syncPermissions($permissions);
        $user->assignRole($role);

        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId($previousTeamId);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
