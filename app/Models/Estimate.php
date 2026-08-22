<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstimateStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Estimate extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'estimate_no',
        'customer_id',
        'estimate_date',
        'expiry_date',
        'subtotal',
        'discount',
        'vat_total',
        'total',
        'status',
        'converted_invoice_id',
        'reference',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'estimate_date' => 'date',
            'expiry_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'vat_total' => 'decimal:2',
            'total' => 'decimal:2',
            'status' => EstimateStatus::class,
        ];
    }

    public static function nextEstimateNo(int $companyId): string
    {
        return DocumentNumber::next(self::class, 'estimate_no', 'EST', $companyId);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(EstimateItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function convertedInvoice()
    {
        return $this->belongsTo(SalesInvoice::class, 'converted_invoice_id');
    }
}
