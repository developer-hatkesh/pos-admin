<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Status;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = ['company_id', 'name', 'status'];

    protected function casts(): array
    {
        return [
            'status' => Status::class,
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (Brand $brand): void {
            ProductItem::forgetPosCatalogCache((int) $brand->company_id);

            if ($brand->wasChanged('company_id')) {
                ProductItem::forgetPosCatalogCache((int) $brand->getRawOriginal('company_id'));
            }
        });

        static::deleted(function (Brand $brand): void {
            ProductItem::forgetPosCatalogCache((int) $brand->company_id);
        });
    }

    public function productItems()
    {
        return $this->hasMany(ProductItem::class);
    }
}
