<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountClass extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $primaryKey = 'account_class_id';

    protected $fillable = [
        'account_class_code',
        'account_class_name',
    ];

    public function categories()
    {
        return $this->hasMany(AccountCategory::class, 'account_class_id', 'account_class_id');
    }
}
