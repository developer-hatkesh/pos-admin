<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VariationType extends Model
{
    use HasFactory;

    protected $fillable = ['variation_id', 'name'];

    public function variation()
    {
        return $this->belongsTo(Variation::class);
    }
}
