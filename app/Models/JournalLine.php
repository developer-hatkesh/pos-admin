<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JournalLine extends Model
{
    use HasFactory;

    protected $fillable = ['journal_id', 'ledger_id', 'customer_id', 'supplier_id', 'bank_account_id', 'debit', 'credit', 'description'];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
        ];
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_id');
    }

    public function ledger()
    {
        return $this->belongsTo(Ledger::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }
}
