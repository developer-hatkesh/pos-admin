<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BalanceType;
use App\Enums\LedgerType;
use App\Enums\Status;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ledger extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = ['company_id', 'name', 'nominal_code', 'type', 'parent_id', 'is_control_account', 'opening_balance', 'balance_type', 'status'];

    protected function casts(): array
    {
        return [
            'type' => LedgerType::class,
            'balance_type' => BalanceType::class,
            'status' => Status::class,
            'is_control_account' => 'boolean',
            'opening_balance' => 'decimal:2',
        ];
    }

    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }
    public function children() { return $this->hasMany(self::class, 'parent_id'); }
    public function journalLines() { return $this->hasMany(JournalLine::class); }
}
