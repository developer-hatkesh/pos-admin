<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Support\Str;

abstract class BasePolicy
{
    public function before(User $user): ?bool
    {
        if (method_exists($user, 'hasSuperAdminRole') && $user->hasSuperAdminRole()) {
            return in_array($this->subject(), [
                'AccountCategory',
                'AccountClass',
                'ChartOfAccount',
                'Company',
            ], true);
        }

        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool { return $user->can('ViewAny:'.$this->subject()); }
    public function view(User $user, mixed $model): bool { return $user->can('View:'.$this->subject()); }
    public function create(User $user): bool { return $user->can('Create:'.$this->subject()); }
    public function update(User $user, mixed $model): bool { return $user->can('Update:'.$this->subject()); }
    public function delete(User $user, mixed $model): bool { return $user->can('Delete:'.$this->subject()); }
    public function deleteAny(User $user): bool { return $user->can('DeleteAny:'.$this->subject()); }
    public function restore(User $user, mixed $model): bool { return false; }
    public function restoreAny(User $user): bool { return false; }
    public function forceDelete(User $user, mixed $model): bool { return false; }
    public function forceDeleteAny(User $user): bool { return false; }
    public function replicate(User $user, mixed $model): bool { return $user->can('Replicate:'.$this->subject()); }
    public function reorder(User $user): bool { return $user->can('Reorder:'.$this->subject()); }

    protected function subject(): string
    {
        return Str::of(static::class)->classBasename()->beforeLast('Policy')->toString();
    }
}
