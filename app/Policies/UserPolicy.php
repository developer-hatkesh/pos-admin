<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy extends BasePolicy
{
    public function view(User $user, mixed $model): bool
    {
        return $this->canManageUserRecord($user, $model) && parent::view($user, $model);
    }

    public function update(User $user, mixed $model): bool
    {
        return $this->canManageUserRecord($user, $model) && parent::update($user, $model);
    }

    public function delete(User $user, mixed $model): bool
    {
        return $this->canManageUserRecord($user, $model) && parent::delete($user, $model);
    }

    public function replicate(User $user, mixed $model): bool
    {
        return $this->canManageUserRecord($user, $model) && parent::replicate($user, $model);
    }

    private function canManageUserRecord(User $authUser, mixed $model): bool
    {
        if (! $model instanceof User) {
            return true;
        }

        return $authUser->isSuperAdmin() || ! $model->isSuperAdmin();
    }
}
