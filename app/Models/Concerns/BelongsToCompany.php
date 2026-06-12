<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::creating(function ($model): void {
            if ($model->company_id === null && auth()->check() && auth()->user()->company_id !== null) {
                $model->company_id = auth()->user()->company_id;
            }
        });

        static::addGlobalScope('company', function (Builder $builder): void {
            if (! auth()->check()) {
                return;
            }

            $user = auth()->user();

            if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
                return;
            }

            if ($user->company_id !== null) {
                $builder->where($builder->getModel()->getTable().'.company_id', $user->company_id);
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
