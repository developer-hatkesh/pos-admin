<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoucherAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'voucher_id',
        'sales_invoice_id',
        'purchase_invoice_id',
        'expense_id',
        'sales_return_id',
        'purchase_return_id',
        'income_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

    public function salesInvoice()
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    public function purchaseInvoice()
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }

    public function salesReturn()
    {
        return $this->belongsTo(SalesReturn::class);
    }

    public function purchaseReturn()
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    public function income()
    {
        return $this->belongsTo(Income::class);
    }
}
