<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ItemUnit;
use App\Enums\ProductType;
use App\Enums\Status;
use App\Enums\StockMovementType;
use App\Enums\TaxType;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ProductItem extends Model implements HasMedia
{
    use BelongsToCompany, HasFactory, InteractsWithMedia, LogsModelActivity;

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

        static::saved(function (ProductItem $productItem): void {
            self::forgetCompanyCaches((int) $productItem->company_id);

            if ($productItem->wasChanged('company_id')) {
                self::forgetCompanyCaches((int) $productItem->getRawOriginal('company_id'));
            }
        });

        static::deleted(function (ProductItem $productItem): void {
            self::forgetCompanyCaches((int) $productItem->company_id);
        });
    }

    public static function cachedSelectOptions(int $companyId): array
    {
        if ($companyId < 1) {
            return [];
        }

        return Cache::rememberForever(self::selectOptionsCacheKey($companyId), fn (): array => self::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->mapWithKeys(fn (ProductItem $productItem): array => [
                $productItem->id => $productItem->name,
            ])
            ->all());
    }

    public static function forgetSelectOptionsCache(int $companyId): bool
    {
        return $companyId > 0 && Cache::forget(self::selectOptionsCacheKey($companyId));
    }

    public static function cachedPosCatalog(int $companyId): array
    {
        if ($companyId < 1) {
            return [];
        }

        return Cache::rememberForever(self::posCatalogCacheKey($companyId), fn (): array => self::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where(function ($query): void {
                $query->where('product_type', '!=', 'variation')
                    ->orWhereNotNull('variation_type_id');
            })
            ->where('status', Status::Active->value)
            ->with(['category:id,name', 'brand:id,name', 'media'])
            ->orderBy('name')
            ->orderBy('id')
            ->get([
                'id',
                'company_id',
                'category_id',
                'brand_id',
                'item_code',
                'sku',
                'barcode',
                'name',
                'sale_price',
                'wholesale_price',
                'image_urls',
            ])
            ->mapWithKeys(fn (ProductItem $productItem): array => [
                $productItem->id => [
                    'id' => $productItem->id,
                    'category_id' => $productItem->category_id,
                    'brand_id' => $productItem->brand_id,
                    'item_code' => $productItem->item_code,
                    'sku' => $productItem->sku,
                    'barcode' => $productItem->barcode,
                    'name' => $productItem->name,
                    'retail_price' => (float) $productItem->sale_price,
                    'wholesale_price' => (float) $productItem->wholesale_price,
                    'first_product_image_url' => $productItem->first_product_image_url,
                    'brand_name' => $productItem->brand?->name,
                    'category_name' => $productItem->category?->name,
                ],
            ])
            ->all());
    }

    public static function forgetPosCatalogCache(int $companyId): bool
    {
        return $companyId > 0 && Cache::forget(self::posCatalogCacheKey($companyId));
    }

    public static function forgetCompanyCaches(int $companyId): void
    {
        self::forgetSelectOptionsCache($companyId);
        self::forgetPosCatalogCache($companyId);
    }

    private static function selectOptionsCacheKey(int $companyId): string
    {
        return 'product-items:select-options:v2:company:'.$companyId;
    }

    private static function posCatalogCacheKey(int $companyId): string
    {
        return 'product-items:pos-catalog:company:'.$companyId;
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

        self::forgetPosCatalogCache((int) $this->company_id);
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

    public function defaultTaxRateId(): int
    {
        return (int) ($this->tax_rate_id ?: TaxRate::idForRate($this->vat_rate) ?: TaxRate::defaultId());
    }

    public function defaultVatRate(): float
    {
        return TaxRate::rateFor($this->defaultTaxRateId());
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
