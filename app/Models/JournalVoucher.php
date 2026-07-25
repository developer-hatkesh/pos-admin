<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JournalVoucher extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'voucher_no', 'voucher_date', 'form_type', 'sales_return_id',
        'journal_id', 'reference', 'narration', 'created_by',
    ];

    protected function casts(): array
    {
        return ['voucher_date' => 'date'];
    }

    public static function nextVoucherNo(int $companyId): string
    {
        return DocumentNumber::next(self::class, 'voucher_no', 'JV', $companyId);
    }

    protected static function booted(): void
    {
        static::creating(function (JournalVoucher $voucher): void {
            $voucher->voucher_no = $voucher->voucher_no ?: self::nextVoucherNo($voucher->company_id);
            $voucher->created_by = $voucher->created_by ?: auth()->id();
        });
    }

    public function salesReturn()
    {
        return $this->belongsTo(SalesReturn::class);
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_id');
    }

    public function allocations()
    {
        return $this->hasMany(JournalVoucherAllocation::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
