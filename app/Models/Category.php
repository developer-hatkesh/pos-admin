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

    public function productItems()
    {
        return $this->hasMany(ProductItem::class);
    }
}
