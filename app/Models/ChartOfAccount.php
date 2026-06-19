<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChartOfAccount extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $primaryKey = 'account_id';

    protected $fillable = [
        'account_category_id',
        'account_code',
        'account_name',
        'normal_balance_type',
        'opening_balance',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function accountCategory()
    {
        return $this->belongsTo(AccountCategory::class, 'account_category_id', 'account_category_id');
    }
}
