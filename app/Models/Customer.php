<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BalanceType;
use App\Enums\LedgerType;
use App\Enums\Status;
use App\Models\Concerns\BelongsToCompany;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'customer_code', 'name', 'company_name', 'contact_person', 'phone',
        'mobile_no', 'telephone_no', 'email', 'website', 'vat_number', 'tax_number',
        'billing_address', 'delivery_address', 'address_line1', 'address_line2', 'city',
        'postcode', 'country', 'currency_id', 'tax_code_id', 'discount_percent',
        'price_type', 'payment_terms', 'payment_terms_days', 'credit_limit', 'opening_balance',
        'balance_type', 'ledger_id', 'chart_account_id', 'notes', 'status',
    ];

    protected function casts(): array
    {
        return [
            'balance_type' => BalanceType::class,
            'status' => Status::class,
            'payment_terms_days' => 'integer',
            'discount_percent' => 'decimal:2',
            'credit_limit' => 'decimal:2',
            'opening_balance' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Customer $customer): void {
            if (blank($customer->customer_code)) {
                $customer->customer_code = self::nextCustomerCode($customer->company_id);
            }

            $customer->name = $customer->name ?: ($customer->company_name ?: $customer->customer_code);
            $customer->company_name = $customer->company_name ?: $customer->name;
            $customer->phone = $customer->telephone_no ?: ($customer->mobile_no ?: $customer->phone);
            $customer->vat_number = $customer->tax_number ?: $customer->vat_number;
            $customer->price_type = in_array($customer->price_type, ['retail', 'wholesale'], true) ? $customer->price_type : 'retail';
            $customer->chart_account_id = $customer->chart_account_id ?: self::accountReceivableLedgerId($customer->company_id);
            $customer->ledger_id = $customer->chart_account_id ?: $customer->ledger_id;
        });

        static::saving(function (Customer $customer): void {
            if ($customer->exists) {
                $originalName = $customer->getOriginal('name');
                $originalCompanyName = $customer->getOriginal('company_name');

                if ($customer->isDirty('company_name') && ($originalName === $originalCompanyName || blank($originalName))) {
                    $customer->name = $customer->company_name ?: $customer->name;
                }
            }

            $customer->name = $customer->name ?: ($customer->company_name ?: $customer->customer_code);
            $customer->company_name = $customer->company_name ?: $customer->name;
            $customer->phone = $customer->telephone_no ?: ($customer->mobile_no ?: $customer->phone);
            $customer->vat_number = $customer->tax_number ?: $customer->vat_number;
            $customer->price_type = in_array($customer->price_type, ['retail', 'wholesale'], true) ? $customer->price_type : 'retail';
            $customer->chart_account_id = $customer->chart_account_id ?: self::accountReceivableLedgerId($customer->company_id);
            $customer->ledger_id = $customer->chart_account_id ?: $customer->ledger_id;
        });
    }

    public static function nextCustomerCode(?int $companyId): string
    {
        return DocumentNumber::next(self::class, 'customer_code', 'CUST', $companyId);
    }

    private static function accountReceivableLedgerId(?int $companyId): ?int
    {
        if ($companyId === null) {
            return null;
        }

        $ledger = Ledger::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->whereIn('nominal_code', ['1200', '1100'])
            ->get()
            ->first(function (Ledger $ledger): bool {
                $text = strtolower($ledger->nominal_code.' '.$ledger->name);

                return str_contains($text, 'receivable')
                    || str_contains($text, 'debtor')
                    || str_contains($text, 'trade debt');
            });

        if ($ledger) {
            return $ledger->id;
        }

        $chartAccount = ChartOfAccount::query()
            ->where('account_code', '1200')
            ->where('is_active', true)
            ->first();

        if (! $chartAccount) {
            return null;
        }

        return Ledger::query()
            ->withoutGlobalScope('company')
            ->firstOrCreate(
                ['company_id' => $companyId, 'nominal_code' => $chartAccount->account_code],
                [
                    'name' => $chartAccount->account_name,
                    'type' => LedgerType::Asset,
                    'is_control_account' => true,
                    'opening_balance' => 0,
                    'balance_type' => BalanceType::Debit,
                    'status' => Status::Active,
                ],
            )->id;
    }

    public function ledger()
    {
        return $this->belongsTo(Ledger::class);
    }

    public function chartAccount()
    {
        return $this->belongsTo(Ledger::class, 'chart_account_id');
    }

    public function salesInvoices()
    {
        return $this->hasMany(SalesInvoice::class);
    }

    public function bankTransactions()
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function vouchers()
    {
        return $this->hasMany(Voucher::class);
    }

    public function salesReturns()
    {
        return $this->hasMany(SalesReturn::class);
    }
}
