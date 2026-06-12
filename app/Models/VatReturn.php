<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VatReturnStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VatReturn extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = ['company_id', 'period_start', 'period_end', 'box1', 'box2', 'box4', 'box6', 'box7', 'box8', 'box9', 'status', 'submitted_at'];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'box1' => 'decimal:2',
            'box2' => 'decimal:2',
            'box4' => 'decimal:2',
            'box6' => 'decimal:2',
            'box7' => 'decimal:2',
            'box8' => 'decimal:2',
            'box9' => 'decimal:2',
            'status' => VatReturnStatus::class,
            'submitted_at' => 'datetime',
        ];
    }
}
