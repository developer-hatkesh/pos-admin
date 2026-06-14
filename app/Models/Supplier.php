<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BalanceType;
use App\Enums\PaymentTerms;
use App\Enums\Status;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'name', 'phone', 'email', 'address_line1', 'address_line2', 'city',
        'postcode', 'country', 'vat_number', 'payment_terms', 'credit_limit',
        'opening_balance', 'balance_type', 'ledger_id', 'status',
    ];

    protected function casts(): array
    {
        return [
            'payment_terms' => PaymentTerms::class,
            'balance_type' => BalanceType::class,
            'status' => Status::class,
            'credit_limit' => 'decimal:2',
            'opening_balance' => 'decimal:2',
        ];
    }

    public function ledger() { return $this->belongsTo(Ledger::class); }
    public function purchaseInvoices() { return $this->hasMany(PurchaseInvoice::class); }
    public function bankTransactions() { return $this->hasMany(BankTransaction::class); }
}
