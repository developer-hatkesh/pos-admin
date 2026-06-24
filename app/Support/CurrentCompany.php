<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Company;
use App\Models\User;

class CurrentCompany
{
    public const SESSION_KEY = 'current_company_id';

    public function id(): ?int
    {
        $user = auth()->user();

        if ($user instanceof User && ! $this->canSwitchCompany($user)) {
            return $user->company_id;
        }

        $sessionCompanyId = $this->sessionCompanyId();

        if ($sessionCompanyId !== null && Company::query()->whereKey($sessionCompanyId)->exists()) {
            return $sessionCompanyId;
        }

        if (Company::query()->whereKey(1)->exists()) {
            return 1;
        }

        return $user?->company_id ?: Company::query()->orderBy('id')->value('id');
    }

    public function set(int $companyId): void
    {
        if (! Company::query()->whereKey($companyId)->exists()) {
            return;
        }

        session()->put(self::SESSION_KEY, $companyId);
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public function canSwitchCompany(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user instanceof User
            && method_exists($user, 'isAdmin')
            && $user->isAdmin();
    }

    private function sessionCompanyId(): ?int
    {
        if (! app()->bound('session') || ! request()->hasSession()) {
            return null;
        }

        $companyId = session(self::SESSION_KEY);

        return filled($companyId) ? (int) $companyId : null;
    }
}
