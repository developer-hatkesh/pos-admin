<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ItemUnit;
use App\Enums\Status;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductItem extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'category_id', 'brand_id', 'item_code', 'name', 'description', 'unit', 'purchase_price',
        'sale_price', 'vat_rate', 'stock_enabled', 'opening_stock', 'status',
    ];

    protected function casts(): array
    {
        return [
            'unit' => ItemUnit::class,
            'status' => Status::class,
            'stock_enabled' => 'boolean',
            'purchase_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'opening_stock' => 'decimal:3',
        ];
    }

    public function salesInvoiceItems() { return $this->hasMany(SalesInvoiceItem::class); }
    public function purchaseInvoiceItems() { return $this->hasMany(PurchaseInvoiceItem::class); }
    public function stockMovements() { return $this->hasMany(StockMovement::class); }
    public function category() { return $this->belongsTo(Category::class); }
    public function brand() { return $this->belongsTo(Brand::class); }
}
