<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PurchaseReturnStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturn extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'return_no', 'purchase_invoice_id', 'supplier_id', 'return_date',
        'subtotal', 'vat_total', 'total', 'status', 'notes', 'journal_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'return_date' => 'date',
            'subtotal' => 'decimal:2',
            'vat_total' => 'decimal:2',
            'total' => 'decimal:2',
            'status' => PurchaseReturnStatus::class,
        ];
    }

    public static function nextReturnNo(int $companyId, mixed $date = null): string
    {
        return DocumentNumber::next(self::class, 'return_no', 'PR', $companyId);
    }

    protected static function booted(): void
    {
        static::creating(function (PurchaseReturn $return): void {
            if (blank($return->return_no) && $return->company_id !== null) {
                $return->return_no = self::nextReturnNo($return->company_id, $return->return_date);
            }

            $return->created_by = $return->created_by ?: auth()->id();
        });
    }

    public function purchaseInvoice()
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    public function purchaseInvoices()
    {
        return $this->belongsToMany(PurchaseInvoice::class, 'purchase_invoice_purchase_return')->withTimestamps();
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_id');
    }

    public function allocations()
    {
        return $this->hasMany(VoucherAllocation::class);
    }
}
