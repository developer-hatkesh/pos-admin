<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\Concerns\BelongsToCompany;
use App\Services\Accounting\VoucherPostingService;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use BelongsToCompany, HasFactory;

    /** @var array<int, int> */
    private array $salesInvoiceIdsBeforeDelete = [];

    /** @var array<int, int> */
    private array $purchaseInvoiceIdsBeforeDelete = [];

    protected $fillable = [
        'company_id', 'voucher_type', 'payment_voucher_type', 'receipt_voucher_type', 'voucher_no', 'voucher_date', 'bank_account_id',
        'customer_id', 'supplier_id', 'amount', 'reference_no', 'notes', 'status',
        'journal_id', 'bank_transaction_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'voucher_type' => VoucherType::class,
            'voucher_date' => 'date',
            'amount' => 'decimal:2',
            'status' => VoucherStatus::class,
        ];
    }

    public static function nextVoucherNo(int $companyId, VoucherType $type, mixed $date = null): string
    {
        $prefix = $type === VoucherType::Receipt ? 'RV' : 'PV';

        return DocumentNumber::nextWhere(
            self::class,
            'voucher_no',
            $prefix,
            $companyId,
            fn ($query) => $query->where('voucher_type', $type->value),
        );
    }

    protected static function booted(): void
    {
        static::creating(function (Voucher $voucher): void {
            $type = $voucher->voucher_type instanceof VoucherType
                ? $voucher->voucher_type
                : VoucherType::from((string) $voucher->voucher_type);

            if (blank($voucher->voucher_no) && $voucher->company_id !== null) {
                $voucher->voucher_no = self::nextVoucherNo($voucher->company_id, $type, $voucher->voucher_date);
            }

            $voucher->created_by = $voucher->created_by ?: auth()->id();
        });

        static::deleting(function (Voucher $voucher): void {
            $voucher->salesInvoiceIdsBeforeDelete = $voucher->allocations()
                ->whereNotNull('sales_invoice_id')
                ->pluck('sales_invoice_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();
            $voucher->purchaseInvoiceIdsBeforeDelete = $voucher->allocations()
                ->whereNotNull('purchase_invoice_id')
                ->pluck('purchase_invoice_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();
        });

        static::deleted(function (Voucher $voucher): void {
            app(VoucherPostingService::class)->syncSalesInvoiceStatuses($voucher->salesInvoiceIdsBeforeDelete);
            app(VoucherPostingService::class)->syncPurchaseInvoiceStatuses($voucher->purchaseInvoiceIdsBeforeDelete);
        });
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function bankTransaction()
    {
        return $this->belongsTo(BankTransaction::class);
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
