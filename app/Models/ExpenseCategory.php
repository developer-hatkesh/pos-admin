<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = ['company_id', 'category_code', 'category_name', 'ledger_id', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function ledger()
    {
        return $this->belongsTo(Ledger::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
}
