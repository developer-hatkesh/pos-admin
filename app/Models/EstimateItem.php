<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EstimateItem extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['estimate_id', 'product_item_id', 'description', 'qty', 'rate', 'vat_rate', 'tax_rate_id', 'vat_amount', 'line_total', 'sort_order'];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'rate' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function estimate()
    {
        return $this->belongsTo(Estimate::class);
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
