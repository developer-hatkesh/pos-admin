<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Company;
use App\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::creating(function ($model): void {
            if ($model->company_id === null) {
                $model->company_id = app(CurrentCompany::class)->id();
            }
        });

        static::addGlobalScope('company', function (Builder $builder): void {
            if (! auth()->check()) {
                return;
            }

            $companyId = app(CurrentCompany::class)->id();

            if ($companyId !== null) {
                $builder->where($builder->getModel()->getTable().'.company_id', $companyId);
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
