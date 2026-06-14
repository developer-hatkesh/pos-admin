<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesInvoice extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = ['company_id', 'invoice_no', 'party_id', 'customer_id', 'invoice_date', 'due_date', 'subtotal', 'discount', 'vat_total', 'total', 'status', 'journal_id'];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'vat_total' => 'decimal:2',
            'total' => 'decimal:2',
            'status' => InvoiceStatus::class,
        ];
    }

    public function party() { return $this->belongsTo(Party::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function items() { return $this->hasMany(SalesInvoiceItem::class, 'invoice_id'); }
    public function journalEntry() { return $this->belongsTo(JournalEntry::class, 'journal_id'); }
}
