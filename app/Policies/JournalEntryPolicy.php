<?php

declare(strict_types=1);

namespace App\Policies;

class JournalEntryPolicy extends BasePolicy
{
    public function delete($user, mixed $model): bool { return false; }
}
