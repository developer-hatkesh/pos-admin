<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SalesReturnStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class SalesReturn extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'return_no', 'sales_invoice_id', 'customer_id', 'return_date',
        'subtotal', 'vat_total', 'total', 'status', 'notes', 'journal_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'return_date' => 'date',
            'subtotal' => 'decimal:2',
            'vat_total' => 'decimal:2',
            'total' => 'decimal:2',
            'status' => SalesReturnStatus::class,
        ];
    }

    public static function nextReturnNo(int $companyId, mixed $date = null): string
    {
        $returnDate = filled($date) ? Carbon::parse($date) : today();
        $prefix = 'SR-'.$returnDate->format('Ymd').'-';
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
        static::creating(function (SalesReturn $return): void {
            if (blank($return->return_no) && $return->company_id !== null) {
                $return->return_no = self::nextReturnNo($return->company_id, $return->return_date);
            }

            $return->created_by = $return->created_by ?: auth()->id();
        });
    }

    public function salesInvoice()
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    public function salesInvoices()
    {
        return $this->belongsToMany(SalesInvoice::class, 'sales_return_sales_invoice')->withTimestamps();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(SalesReturnItem::class);
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
