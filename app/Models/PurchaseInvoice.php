<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseInvoice extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = ['company_id', 'invoice_no', 'party_id', 'supplier_id', 'invoice_date', 'due_date', 'subtotal', 'vat_total', 'total', 'status', 'journal_id'];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'vat_total' => 'decimal:2',
            'total' => 'decimal:2',
            'status' => InvoiceStatus::class,
        ];
    }

    public function party() { return $this->belongsTo(Party::class); }
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function items() { return $this->hasMany(PurchaseInvoiceItem::class, 'invoice_id'); }
    public function journalEntry() { return $this->belongsTo(JournalEntry::class, 'journal_id'); }
}
