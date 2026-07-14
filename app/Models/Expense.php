<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExpenseStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'voucher_no', 'expense_date', 'expense_category_id', 'supplier_id',
        'payment_bank_account_id', 'payment_date', 'payment_voucher_id',
        'sub_total_amount', 'tax_amount', 'grand_total_amount', 'status', 'notes',
        'file_path', 'journal_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'payment_date' => 'date',
            'sub_total_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'grand_total_amount' => 'decimal:2',
            'status' => ExpenseStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Expense $expense): void {
            if (blank($expense->voucher_no) && $expense->company_id !== null) {
                $expense->voucher_no = self::nextVoucherNo($expense->company_id, $expense->expense_date);
            }

            $expense->created_by = $expense->created_by ?: auth()->id();
        });
    }

    public static function nextVoucherNo(int $companyId, mixed $date = null): string
    {
        return DocumentNumber::next(self::class, 'voucher_no', 'EXP', $companyId, 4);
    }

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function paymentBankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'payment_bank_account_id');
    }

    public function paymentVoucher()
    {
        return $this->belongsTo(Voucher::class, 'payment_voucher_id');
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
