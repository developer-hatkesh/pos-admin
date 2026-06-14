<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ItemUnit;
use App\Enums\Status;
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
        'company_id', 'category_id', 'brand_id', 'item_code', 'barcode', 'name', 'description', 'unit', 'purchase_price',
        'sale_price', 'vat_rate', 'stock_enabled', 'opening_stock', 'image_urls', 'status',
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
            'image_urls' => 'array',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::PRODUCT_IMAGES_COLLECTION)
            ->useDisk('public')
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
}
