<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Status;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = ['company_id', 'ledger_id', 'bank_name', 'account_name', 'account_number', 'sort_code', 'opening_balance', 'status'];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'status' => Status::class,
        ];
    }

    public function ledger() { return $this->belongsTo(Ledger::class); }
    public function bankTransactions() { return $this->hasMany(BankTransaction::class); }
    public function vouchers() { return $this->hasMany(Voucher::class); }

    public function currentBalance(): float
    {
        $deposits = (float) $this->bankTransactions()->where('type', 'deposit')->sum('amount');
        $withdrawals = (float) $this->bankTransactions()->where('type', 'withdrawal')->sum('amount');

        return round((float) $this->opening_balance + $deposits - $withdrawals, 2);
    }
}
