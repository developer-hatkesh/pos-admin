<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Variation extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = ['company_id', 'name'];

    public function types()
    {
        return $this->hasMany(VariationType::class);
    }
}
