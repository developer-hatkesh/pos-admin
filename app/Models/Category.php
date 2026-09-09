<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Status;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
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
        static::saved(function (Category $category): void {
            ProductItem::forgetPosCatalogCache((int) $category->company_id);

            if ($category->wasChanged('company_id')) {
                ProductItem::forgetPosCatalogCache((int) $category->getRawOriginal('company_id'));
            }
        });

        static::deleted(function (Category $category): void {
            ProductItem::forgetPosCatalogCache((int) $category->company_id);
        });
    }

    public function productItems()
    {
        return $this->hasMany(ProductItem::class);
    }
}
