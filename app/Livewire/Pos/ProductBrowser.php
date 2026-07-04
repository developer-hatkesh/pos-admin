<?php

declare(strict_types=1);

namespace App\Livewire\Pos;

use App\Enums\Status;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\ProductItem;
use App\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

class ProductBrowser extends Component
{
    public string $search = '';

    public ?int $selectedCompanyId = null;

    public ?int $selectedCustomerId = null;

    public ?int $categoryId = null;

    public ?int $brandId = null;

    public array $productAddCache = [];

    public array $productOptions = [];

    public array $categoryOptions = [];

    public array $brandOptions = [];

    public function mount(?int $selectedCompanyId = null, ?int $selectedCustomerId = null): void
    {
        $this->selectedCompanyId = $selectedCompanyId;
        $this->selectedCustomerId = $selectedCustomerId;
        $this->loadReferenceData();
        $this->loadProductOptions();
    }

    public function render(): mixed
    {
        return view('livewire.pos.product-browser');
    }

    #[On('pos-customer-selected')]
    public function setSelectedCustomer(?int $customerId): void
    {
        $this->selectedCustomerId = $customerId;
        $this->loadProductOptions();
    }

    public function selectCategory(?int $categoryId): void
    {
        $this->categoryId = $categoryId;
        $this->loadProductOptions();
    }

    public function selectBrand(?int $brandId): void
    {
        $this->brandId = $brandId;
        $this->loadProductOptions();
    }

    public function updatedSearch(): void
    {
        $search = trim($this->search);

        if ($search === '') {
            $this->loadProductOptions();

            return;
        }

        $cachedProduct = collect($this->productAddCache)
            ->first(fn (array $product): bool => in_array($search, array_filter([
                $product['barcode'] ?? null,
                $product['sku'] ?? null,
                $product['item_code'] ?? null,
            ]), true));

        if (is_array($cachedProduct)) {
            $this->addProduct((int) $cachedProduct['id'], true);

            return;
        }

        $exactProducts = $this->exactProductLookupQuery($search)
            ->limit(2)
            ->get(['id']);

        if ($exactProducts->count() === 1) {
            $this->addProduct((int) $exactProducts->first()->id, true);

            return;
        }

        $this->loadProductOptions();
    }

    public function addProduct(int $productId, bool $clearSearch = false): void
    {
        $product = $this->productAddCache[$productId] ?? null;

        if (! $product) {
            $product = $this->productLookupQuery()
                ->whereKey($productId)
                ->first();
        }

        if (! $product) {
            $this->dispatch('pos-focus-search');

            return;
        }

        $this->dispatch('pos-add-product', product: [
            'id' => (int) data_get($product, 'id'),
            'name' => (string) data_get($product, 'name'),
            'item_code' => data_get($product, 'item_code'),
            'barcode' => data_get($product, 'barcode'),
            'sale_price' => $this->productPrice($product),
        ]);

        if ($clearSearch) {
            $this->search = '';
        }

        $this->dispatch('pos-focus-search');
    }

    public function products(): Collection
    {
        return collect($this->productOptions)
            ->map(function (array $product): object {
                $product['brand'] = filled($product['brand_name'] ?? null) ? (object) ['name' => $product['brand_name']] : null;
                $product['category'] = filled($product['category_name'] ?? null) ? (object) ['name' => $product['category_name']] : null;

                return (object) $product;
            });
    }

    public function categories(): Collection
    {
        return collect($this->categoryOptions)->map(fn (array $category): object => (object) $category);
    }

    public function brands(): Collection
    {
        return collect($this->brandOptions)->map(fn (array $brand): object => (object) $brand);
    }

    private function loadReferenceData(): void
    {
        $this->categoryOptions = $this->companyQuery(Category::withoutGlobalScopes())
            ->where('status', Status::Active->value)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
            ])
            ->all();

        $this->brandOptions = $this->companyQuery(Brand::withoutGlobalScopes())
            ->where('status', Status::Active->value)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Brand $brand): array => [
                'id' => $brand->id,
                'name' => $brand->name,
            ])
            ->all();
    }

    private function baseProductQuery(): Builder
    {
        return $this->companyQuery(ProductItem::withoutGlobalScopes())
            ->where(function (Builder $query): void {
                $query->where('product_type', '!=', 'variation')
                    ->orWhereNotNull('variation_type_id');
            })
            ->where('status', Status::Active->value);
    }

    private function filteredProductQuery(): Builder
    {
        return $this->baseProductQuery()
            ->when($this->categoryId, fn (Builder $query): Builder => $query->where('category_id', $this->categoryId))
            ->when($this->brandId, fn (Builder $query): Builder => $query->where('brand_id', $this->brandId))
            ->when(trim($this->search) !== '', function (Builder $query): Builder {
                $search = trim($this->search);

                return $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('item_code', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%")
                        ->orWhereHas('category', fn (Builder $query): Builder => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('brand', fn (Builder $query): Builder => $query->where('name', 'like', "%{$search}%"));
                });
            });
    }

    private function productCardQuery(): Builder
    {
        return $this->filteredProductQuery()
            ->with(['category:id,name', 'brand:id,name'])
            ->select('product_items.*');
    }

    private function productLookupQuery(): Builder
    {
        return $this->baseProductQuery()
            ->select([
                'product_items.id',
                'product_items.company_id',
                'product_items.item_code',
                'product_items.sku',
                'product_items.barcode',
                'product_items.name',
                'product_items.sale_price',
                'product_items.wholesale_price',
            ]);
    }

    private function exactProductLookupQuery(string $search): Builder
    {
        return $this->baseProductQuery()
            ->where(function (Builder $query) use ($search): void {
                $query->where('barcode', $search)
                    ->orWhere('sku', $search)
                    ->orWhere('item_code', $search);
            });
    }

    private function loadProductOptions(): void
    {
        $products = $this->productCardQuery()
            ->orderBy('name')
            ->limit(80)
            ->get();

        $priceType = $this->selectedCustomerPriceType();

        $this->productOptions = $products
            ->map(fn (ProductItem $product): array => [
                'id' => $product->id,
                'item_code' => $product->item_code,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'sale_price' => $this->productPrice($product, $priceType),
                'retail_price' => $product->sale_price,
                'wholesale_price' => $product->wholesale_price,
                'first_product_image_url' => $product->first_product_image_url,
                'brand_name' => $product->brand?->name,
                'category_name' => $product->category?->name,
            ])
            ->all();

        $this->productAddCache = collect($this->productOptions)
            ->mapWithKeys(fn (array $product): array => [
                $product['id'] => [
                    'id' => $product['id'],
                    'item_code' => $product['item_code'],
                    'sku' => $product['sku'],
                    'barcode' => $product['barcode'],
                    'name' => $product['name'],
                    'sale_price' => $product['sale_price'],
                    'retail_price' => $product['retail_price'],
                    'wholesale_price' => $product['wholesale_price'],
                ],
            ])
            ->all();
    }

    private function productPrice(mixed $product, ?string $priceType = null): float
    {
        $retailPrice = (float) data_get($product, 'sale_price', data_get($product, 'retail_price', 0));
        $wholesalePrice = (float) data_get($product, 'wholesale_price', 0);

        if (($priceType ?? $this->selectedCustomerPriceType()) === 'wholesale') {
            return $wholesalePrice;
        }

        return $retailPrice;
    }

    private function selectedCustomerPriceType(): string
    {
        if (! $this->selectedCustomerId) {
            return 'retail';
        }

        return $this->companyQuery(Customer::withoutGlobalScopes())
            ->whereKey($this->selectedCustomerId)
            ->value('price_type') === 'wholesale' ? 'wholesale' : 'retail';
    }

    private function companyQuery(Builder $query): Builder
    {
        $companyId = $this->selectedCompanyId ?? app(CurrentCompany::class)->id();

        return $query->when($companyId, fn (Builder $query): Builder => $query->where('company_id', $companyId));
    }
}
