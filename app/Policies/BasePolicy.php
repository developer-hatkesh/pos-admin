<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

abstract class BasePolicy
{
    protected array $writeRoles = [UserRole::Admin, UserRole::Accountant];

    public function before(User $user): ?bool
    {
        return $this->hasRole($user, UserRole::Admin) ? true : null;
    }

    public function viewAny(User $user): bool { return true; }
    public function view(User $user, mixed $model): bool { return true; }
    public function create(User $user): bool { return $this->canWrite($user); }
    public function update(User $user, mixed $model): bool { return $this->canWrite($user); }
    public function delete(User $user, mixed $model): bool { return $this->canWrite($user); }
    public function restore(User $user, mixed $model): bool { return false; }
    public function forceDelete(User $user, mixed $model): bool { return false; }

    protected function canWrite(User $user): bool
    {
        foreach ($this->writeRoles as $role) {
            if ($this->hasRole($user, $role)) {
                return true;
            }
        }

        return false;
    }

    protected function hasRole(User $user, UserRole $role): bool
    {
        return ($user->role instanceof UserRole ? $user->role : UserRole::tryFrom((string) $user->role)) === $role;
    }
}
