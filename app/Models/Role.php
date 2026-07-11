<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected static function booted(): void
    {
        static::saving(function (Role $role): void {
            $user = auth()->user();

            if ($user instanceof User && ! $user->isSuperAdmin()) {
                $role->company_id = app(CurrentCompany::class)->id();
            }
        });

        static::addGlobalScope('company_roles', function (Builder $builder): void {
            $user = auth()->user();

            if (! $user instanceof User) {
                return;
            }

            $companyId = app(CurrentCompany::class)->id();

            if ($companyId !== null) {
                $builder->where($builder->getModel()->getTable().'.company_id', $companyId);
            }

            if (! $user->isPlatformSuperAdmin()) {
                $builder->where(
                    $builder->getModel()->getTable().'.name',
                    '!=',
                    config('filament-shield.super_admin.name', 'super_admin')
                );
            }
        });
    }

    public function team(): BelongsTo
    {
        $relation = $this->belongsTo(Company::class, 'company_id');
        $companyId = app(CurrentCompany::class)->id();

        if ($companyId !== null) {
            $relation->whereKey($companyId);
        }

        return $relation;
    }
}
