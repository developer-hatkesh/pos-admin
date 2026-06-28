<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ItemUnit;
use App\Enums\ProductType;
use App\Enums\Status;
use App\Enums\StockMovementType;
use App\Enums\TaxType;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ProductItem extends Model implements HasMedia
{
    use BelongsToCompany, HasFactory, InteractsWithMedia;

    public const PRODUCT_IMAGES_COLLECTION = 'product_images';

    protected $fillable = [
        'company_id', 'category_id', 'brand_id', 'item_code', 'barcode', 'name', 'product_type', 'parent_product_item_id',
        'variation_id', 'variation_type_id', 'sku', 'description', 'unit', 'purchase_price', 'sale_price', 'wholesale_price', 'vat_rate',
        'tax_rate_id', 'tax_type', 'stock_enabled', 'opening_stock', 'current_stock', 'stock_alert_qty', 'expiry_date', 'image_urls', 'status',
    ];

    protected function casts(): array
    {
        return [
            'unit' => ItemUnit::class,
            'product_type' => ProductType::class,
            'status' => Status::class,
            'tax_type' => TaxType::class,
            'stock_enabled' => 'boolean',
            'purchase_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'opening_stock' => 'decimal:3',
            'current_stock' => 'decimal:3',
            'stock_alert_qty' => 'decimal:3',
            'expiry_date' => 'date',
            'image_urls' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ProductItem $productItem): void {
            if ($productItem->product_type === ProductType::Service) {
                $productItem->stock_enabled = false;
                $productItem->opening_stock = 0;
                $productItem->current_stock = 0;
                $productItem->stock_alert_qty = null;
                $productItem->expiry_date = null;
            }

            if ($productItem->product_type !== ProductType::Variation) {
                $productItem->parent_product_item_id = null;
                $productItem->variation_id = null;
                $productItem->variation_type_id = null;
            }

            if (! $productItem->exists && ! array_key_exists('current_stock', $productItem->getAttributes())) {
                $productItem->current_stock = $productItem->stock_enabled ? (float) $productItem->opening_stock : 0;
            }

            if ($productItem->exists && $productItem->isDirty('opening_stock') && ! $productItem->isDirty('current_stock')) {
                $openingDelta = (float) $productItem->opening_stock - (float) $productItem->getOriginal('opening_stock');
                $productItem->current_stock = round((float) $productItem->getOriginal('current_stock') + $openingDelta, 3);
            }

            if (! $productItem->stock_enabled) {
                $productItem->current_stock = 0;
            }
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::PRODUCT_IMAGES_COLLECTION)
            ->useDisk(config('media-library.disk_name', 'public'))
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
    }

    public function syncProductImageUrls(): void
    {
        $this->forceFill([
            'image_urls' => $this->getMedia(self::PRODUCT_IMAGES_COLLECTION)
                ->map->getUrl()
                ->values()
                ->all(),
        ])->saveQuietly();
    }

    public function getFirstProductImageUrlAttribute(): ?string
    {
        return $this->image_urls[0] ?? $this->getFirstMediaUrl(self::PRODUCT_IMAGES_COLLECTION) ?: null;
    }

    public function salesInvoiceItems()
    {
        return $this->hasMany(SalesInvoiceItem::class);
    }

    public function purchaseInvoiceItems()
    {
        return $this->hasMany(PurchaseInvoiceItem::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function taxRate()
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function parentProductItem()
    {
        return $this->belongsTo(self::class, 'parent_product_item_id');
    }

    public function variationChildren()
    {
        return $this->hasMany(self::class, 'parent_product_item_id');
    }

    public function variation()
    {
        return $this->belongsTo(Variation::class);
    }

    public function variationType()
    {
        return $this->belongsTo(VariationType::class);
    }

    public function getCurrentStockAttribute(): float
    {
        if (array_key_exists('current_stock', $this->attributes)) {
            return (float) $this->attributes['current_stock'];
        }

        if (! $this->stock_enabled || $this->product_type === ProductType::Service) {
            return 0.0;
        }

        $movements = $this->relationLoaded('stockMovements')
            ? $this->stockMovements
            : $this->stockMovements()->get(['type', 'quantity']);

        $movementTotal = $movements->sum(function (StockMovement $movement): float {
            $type = $movement->type;
            $quantity = (float) $movement->quantity;

            if (! $type instanceof StockMovementType) {
                $type = StockMovementType::tryFrom((string) $type);
            }

            return $type?->increasesStock() ? $quantity : -$quantity;
        });

        return (float) $this->opening_stock + (float) $movementTotal;
    }
}
