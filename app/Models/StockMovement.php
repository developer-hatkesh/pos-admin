<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockMovementType;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use BelongsToCompany, HasFactory;

    public $timestamps = false;

    protected $fillable = ['company_id', 'item_id', 'type', 'quantity', 'rate', 'reference_type', 'reference_id', 'movement_date', 'created_at'];

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'decimal:3',
            'rate' => 'decimal:2',
            'movement_date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function item() { return $this->belongsTo(Item::class); }
}
