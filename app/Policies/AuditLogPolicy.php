<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class AuditLogPolicy extends BasePolicy
{
    public function create(User $user): bool { return false; }
    public function update(User $user, mixed $model): bool { return false; }
    public function delete(User $user, mixed $model): bool { return false; }
}
