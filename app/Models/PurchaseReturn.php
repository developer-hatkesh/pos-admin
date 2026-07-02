<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PurchaseReturnStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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
        $returnDate = filled($date) ? Carbon::parse($date) : today();
        $prefix = 'PR-'.$returnDate->format('Ymd').'-';
        $latest = self::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('return_no', 'like', $prefix.'%')
            ->orderByDesc('return_no')
            ->value('return_no');

        $next = $latest ? ((int) substr($latest, -4)) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
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
