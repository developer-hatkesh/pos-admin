<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountCategory extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $primaryKey = 'account_category_id';

    protected $fillable = [
        'account_class_id',
        'account_category_code',
        'account_category_name',
    ];

    public function accountClass()
    {
        return $this->belongsTo(AccountClass::class, 'account_class_id', 'account_class_id');
    }
}
