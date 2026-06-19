<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesInvoiceItem extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['invoice_id', 'item_id', 'product_item_id', 'description', 'qty', 'rate', 'vat_rate', 'tax_rate_id', 'vat_amount', 'line_total'];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'rate' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function salesInvoice()
    {
        return $this->belongsTo(SalesInvoice::class, 'invoice_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function productItem()
    {
        return $this->belongsTo(ProductItem::class);
    }

    public function taxRate()
    {
        return $this->belongsTo(TaxRate::class);
    }
}
