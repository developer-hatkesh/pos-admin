<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BalanceType;
use App\Enums\Status;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'supplier_code', 'name', 'company_name', 'contact_person', 'phone',
        'mobile_no', 'telephone_no', 'email', 'website', 'vat_number', 'tax_number',
        'address', 'address_line1', 'address_line2', 'city', 'postcode', 'country',
        'currency_id', 'payment_terms', 'credit_limit', 'opening_balance', 'balance_type',
        'ledger_id', 'chart_account_id', 'bank_name', 'notes', 'status',
    ];

    protected function casts(): array
    {
        return [
            'balance_type' => BalanceType::class,
            'status' => Status::class,
            'payment_terms' => 'integer',
            'credit_limit' => 'decimal:2',
            'opening_balance' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Supplier $supplier): void {
            if (blank($supplier->supplier_code)) {
                $supplier->supplier_code = self::nextSupplierCode();
            }

            $supplier->name = $supplier->company_name ?: ($supplier->name ?: $supplier->supplier_code);
            $supplier->phone = $supplier->telephone_no ?: ($supplier->mobile_no ?: $supplier->phone);
            $supplier->vat_number = $supplier->tax_number ?: $supplier->vat_number;
            $supplier->chart_account_id = $supplier->chart_account_id ?: self::accountPayableLedgerId($supplier->company_id);
            $supplier->ledger_id = $supplier->chart_account_id ?: $supplier->ledger_id;
        });

        static::saving(function (Supplier $supplier): void {
            $supplier->name = $supplier->company_name ?: ($supplier->name ?: $supplier->supplier_code);
            $supplier->phone = $supplier->telephone_no ?: ($supplier->mobile_no ?: $supplier->phone);
            $supplier->vat_number = $supplier->tax_number ?: $supplier->vat_number;
            $supplier->chart_account_id = $supplier->chart_account_id ?: self::accountPayableLedgerId($supplier->company_id);
            $supplier->ledger_id = $supplier->chart_account_id ?: $supplier->ledger_id;
        });
    }

    private static function nextSupplierCode(): string
    {
        $lastId = self::query()
            ->withoutGlobalScope('company')
            ->max('id') ?? 0;

        return sprintf('SUP%03d', $lastId + 1);
    }

    private static function accountPayableLedgerId(?int $companyId): ?int
    {
        if ($companyId === null) {
            return null;
        }

        return Ledger::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('nominal_code', '2100')
            ->value('id');
    }

    public function ledger() { return $this->belongsTo(Ledger::class); }
    public function chartAccount() { return $this->belongsTo(Ledger::class, 'chart_account_id'); }
    public function purchaseInvoices() { return $this->hasMany(PurchaseInvoice::class); }
    public function bankTransactions() { return $this->hasMany(BankTransaction::class); }
}
