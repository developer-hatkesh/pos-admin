<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'contact_person_name', 'email', 'phone', 'website', 'additional_information',
        'address', 'city', 'postcode', 'country', 'number_of_employees', 'vat_number',
        'company_house_number', 'business_phone_number', 'currency', 'legal_business_name',
        'financial_year_start', 'financial_year_end', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'financial_year_start' => 'date',
            'financial_year_end' => 'date',
        ];
    }

    public function users() { return $this->hasMany(User::class); }
    public function customers() { return $this->hasMany(Customer::class); }
    public function suppliers() { return $this->hasMany(Supplier::class); }
    public function productItems() { return $this->hasMany(ProductItem::class); }
    public function brands() { return $this->hasMany(Brand::class); }
    public function categories() { return $this->hasMany(Category::class); }
    public function parties() { return $this->hasMany(Party::class); }
    public function ledgers() { return $this->hasMany(Ledger::class); }
    public function items() { return $this->hasMany(Item::class); }
    public function salesInvoices() { return $this->hasMany(SalesInvoice::class); }
    public function purchaseInvoices() { return $this->hasMany(PurchaseInvoice::class); }
    public function bankAccounts() { return $this->hasMany(BankAccount::class); }
    public function paymentMethods() { return $this->hasMany(PaymentMethod::class); }
    public function bankTransactions() { return $this->hasMany(BankTransaction::class); }
    public function journalEntries() { return $this->hasMany(JournalEntry::class); }
    public function vatReturns() { return $this->hasMany(VatReturn::class); }
    public function stockMovements() { return $this->hasMany(StockMovement::class); }
    public function auditLogs() { return $this->hasMany(AuditLog::class); }
}
