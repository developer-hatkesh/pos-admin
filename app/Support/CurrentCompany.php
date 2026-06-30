<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class CurrentCompany
{
    public const SESSION_KEY = 'current_company_id';

    public function id(): ?int
    {
        $user = auth()->user();
        $companies = $user instanceof User ? $this->companiesFor($user) : collect();

        $sessionCompanyId = $this->sessionCompanyId();

        if (
            $sessionCompanyId !== null
            && ($companies->isNotEmpty()
                ? $companies->contains('id', $sessionCompanyId)
                : Company::query()->whereKey($sessionCompanyId)->exists())
        ) {
            return $sessionCompanyId;
        }

        if ($user instanceof User && $companies->isNotEmpty()) {
            if ($user->company_id !== null && $companies->contains('id', (int) $user->company_id)) {
                return (int) $user->company_id;
            }

            return (int) $companies->first()->id;
        }

        if (Company::query()->whereKey(1)->exists()) {
            return 1;
        }

        return $user?->company_id ?: Company::query()->orderBy('id')->value('id');
    }

    public function set(int $companyId): void
    {
        $user = auth()->user();

        if ($user instanceof User && ! $this->canAccessCompany($companyId, $user)) {
            return;
        }

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

        return $user instanceof User && $this->companiesFor($user)->count() > 1;
    }

    public function canAccessCompany(int $companyId, ?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user instanceof User && $this->companiesFor($user)->contains('id', $companyId);
    }

    public function companiesFor(?User $user = null): Collection
    {
        $user ??= auth()->user();

        if (! $user instanceof User) {
            return collect();
        }

        $companies = Schema::hasTable('company_user')
            ? $user->companies()
                ->orderBy('name')
                ->get(['companies.id', 'companies.name'])
            : collect();

        if ($user->company_id !== null) {
            $defaultCompany = Company::query()
                ->whereKey($user->company_id)
                ->orderBy('name')
                ->get(['id', 'name']);

            $companies = $companies
                ->merge($defaultCompany)
                ->unique('id')
                ->sortBy('name')
                ->values();
        }

        if ($companies->isEmpty() && $user->isAdmin()) {
            return Company::query()
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return $companies;
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
