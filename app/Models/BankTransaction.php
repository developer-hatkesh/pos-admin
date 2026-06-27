<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BankTransactionType;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankTransaction extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = ['bank_account_id', 'company_id', 'transaction_date', 'type', 'amount', 'reference', 'party_id', 'customer_id', 'supplier_id', 'ledger_id', 'journal_id', 'reconciled'];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'type' => BankTransactionType::class,
            'amount' => 'decimal:2',
            'reconciled' => 'boolean',
        ];
    }

    public function bankAccount() { return $this->belongsTo(BankAccount::class); }
    public function party() { return $this->belongsTo(Party::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function ledger() { return $this->belongsTo(Ledger::class); }
    public function journalEntry() { return $this->belongsTo(JournalEntry::class, 'journal_id'); }
    public function voucher() { return $this->hasOne(Voucher::class); }
}
