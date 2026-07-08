<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    use HandlesAuthorization;

    public function before(User $authUser): ?bool
    {
        if (method_exists($authUser, 'hasSuperAdminRole') && $authUser->hasSuperAdminRole()) {
            return false;
        }

        return $authUser->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $authUser): bool
    {
        return $authUser->can('ViewAny:Role');
    }

    public function view(User $authUser, Role $role): bool
    {
        return $this->canManageRoleRecord($authUser, $role) && $authUser->can('View:Role');
    }

    public function create(User $authUser): bool
    {
        return $authUser->can('Create:Role');
    }

    public function update(User $authUser, Role $role): bool
    {
        return $this->canManageRoleRecord($authUser, $role) && $authUser->can('Update:Role');
    }

    public function delete(User $authUser, Role $role): bool
    {
        return $this->canManageRoleRecord($authUser, $role) && $authUser->can('Delete:Role');
    }

    public function deleteAny(User $authUser): bool
    {
        return $authUser->can('DeleteAny:Role');
    }

    public function restore(User $authUser, Role $role): bool
    {
        return $authUser->can('Restore:Role');
    }

    public function forceDelete(User $authUser, Role $role): bool
    {
        return $authUser->can('ForceDelete:Role');
    }

    public function forceDeleteAny(User $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Role');
    }

    public function restoreAny(User $authUser): bool
    {
        return $authUser->can('RestoreAny:Role');
    }

    public function replicate(User $authUser, Role $role): bool
    {
        return $authUser->can('Replicate:Role');
    }

    public function reorder(User $authUser): bool
    {
        return $authUser->can('Reorder:Role');
    }

    private function canManageRoleRecord(User $authUser, Role $role): bool
    {
        if ($role->name === config('filament-shield.super_admin.name', 'super_admin')) {
            return false;
        }

        return $role->company_id !== null;
    }
}
