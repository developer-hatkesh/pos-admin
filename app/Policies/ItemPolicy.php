<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;

class ItemPolicy extends BasePolicy
{
    protected array $writeRoles = [UserRole::Admin, UserRole::Accountant, UserRole::Sales];
}
