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

    public string $customerPriceType = 'retail';

    public array $productAddCache = [];

    public array $productOptions = [];

    public array $categoryOptions = [];

    public array $brandOptions = [];

    public function mount(?int $selectedCompanyId = null, ?int $selectedCustomerId = null): void
    {
        $this->selectedCompanyId = $selectedCompanyId;
        $this->selectedCustomerId = $selectedCustomerId;
        $this->customerPriceType = $this->resolveSelectedCustomerPriceType();
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
        $this->customerPriceType = $this->resolveSelectedCustomerPriceType();
        $this->loadProductOptions();
    }

    #[On('pos-product-search-updated')]
    public function setSearch(string $search): void
    {
        $this->search = $search;
        $this->updatedSearch();
    }

    public function selectCategory(?int $categoryId): void
    {
        $this->categoryId = $categoryId;
        $this->loadProductOptions();
        $this->dispatch('pos-focus-search');
    }

    public function selectBrand(?int $brandId): void
    {
        $this->brandId = $brandId;
        $this->loadProductOptions();
        $this->dispatch('pos-focus-search');
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

        $exactProducts = $this->posCatalog()
            ->filter(fn (array $product): bool => in_array($search, array_filter([
                $product['barcode'] ?? null,
                $product['sku'] ?? null,
                $product['item_code'] ?? null,
            ]), true))
            ->take(2);

        if ($exactProducts->count() === 1) {
            $this->addProduct((int) $exactProducts->first()['id'], true);

            return;
        }

        $this->loadProductOptions();
    }

    public function addProduct(int $productId, bool $clearSearch = false): void
    {
        $product = $this->productAddCache[$productId] ?? null;

        if (! $product) {
            $product = $this->posCatalog()->get($productId);
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
            $this->dispatch('pos-product-search-cleared');
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

    private function loadProductOptions(): void
    {
        $search = mb_strtolower(trim($this->search));
        $products = $this->posCatalog()
            ->when($this->categoryId, fn (Collection $products): Collection => $products->where('category_id', $this->categoryId))
            ->when($this->brandId, fn (Collection $products): Collection => $products->where('brand_id', $this->brandId))
            ->when($search !== '', fn (Collection $products): Collection => $products->filter(
                fn (array $product): bool => str_contains(mb_strtolower(implode(' ', array_filter([
                    $product['name'] ?? null,
                    $product['item_code'] ?? null,
                    $product['sku'] ?? null,
                    $product['barcode'] ?? null,
                    $product['category_name'] ?? null,
                    $product['brand_name'] ?? null,
                ]))), $search),
            ))
            ->take(80);

        $this->productOptions = $products
            ->map(fn (array $product): array => [
                'id' => $product['id'],
                'item_code' => $product['item_code'],
                'sku' => $product['sku'],
                'barcode' => $product['barcode'],
                'name' => $product['name'],
                'sale_price' => $this->productPrice($product),
                'retail_price' => $product['retail_price'],
                'wholesale_price' => $product['wholesale_price'],
                'first_product_image_url' => $product['first_product_image_url'],
                'brand_name' => $product['brand_name'],
                'category_name' => $product['category_name'],
            ])
            ->values()
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

    private function posCatalog(): Collection
    {
        $companyId = $this->selectedCompanyId ?? app(CurrentCompany::class)->id();

        return collect(once(fn (): array => ProductItem::cachedPosCatalog((int) ($companyId ?? 0))));
    }

    private function productPrice(mixed $product, ?string $priceType = null): float
    {
        $retailPrice = (float) data_get($product, 'sale_price', data_get($product, 'retail_price', 0));
        $wholesalePrice = (float) data_get($product, 'wholesale_price', 0);

        if (($priceType ?? $this->customerPriceType) === 'wholesale') {
            return $wholesalePrice;
        }

        return $retailPrice;
    }

    private function resolveSelectedCustomerPriceType(): string
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
