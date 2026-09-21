<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Status;
use App\Livewire\Pos\ProductBrowser;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ProductItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PosProductCatalogCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_is_company_scoped_and_invalidated_by_product_and_reference_changes(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id, 'name' => 'Spirits']);
        $brand = Brand::factory()->create(['company_id' => $company->id, 'name' => 'North Star']);
        $product = ProductItem::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Cached Whisky',
            'item_code' => 'WHISKY-1',
            'barcode' => '500000000001',
            'sale_price' => 25,
            'wholesale_price' => 20,
        ]);
        ProductItem::factory()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Other Company Product',
        ]);
        ProductItem::factory()->create([
            'company_id' => $company->id,
            'name' => 'Inactive Product',
            'status' => Status::Inactive,
        ]);

        $catalog = ProductItem::cachedPosCatalog($company->id);

        $this->assertSame([$product->id], array_keys($catalog));
        $this->assertSame('North Star', $catalog[$product->id]['brand_name']);
        $this->assertSame('Spirits', $catalog[$product->id]['category_name']);
        $this->assertSame(25.0, $catalog[$product->id]['retail_price']);

        $product->update(['sale_price' => 30]);
        $this->assertSame(30.0, ProductItem::cachedPosCatalog($company->id)[$product->id]['retail_price']);

        $brand->update(['name' => 'Updated Brand']);
        $this->assertSame('Updated Brand', ProductItem::cachedPosCatalog($company->id)[$product->id]['brand_name']);

        $category->update(['name' => 'Updated Category']);
        $this->assertSame('Updated Category', ProductItem::cachedPosCatalog($company->id)[$product->id]['category_name']);

        $product->delete();
        $this->assertSame([], ProductItem::cachedPosCatalog($company->id));
    }

    public function test_browser_filters_cached_products_and_uses_cache_for_exact_barcode_matches(): void
    {
        $company = Company::factory()->create();
        $this->actingAs(User::factory()->create(['company_id' => $company->id]));
        $customer = Customer::factory()->create([
            'company_id' => $company->id,
            'price_type' => 'wholesale',
        ]);
        $product = ProductItem::factory()->create([
            'company_id' => $company->id,
            'name' => 'Searchable Whisky',
            'item_code' => 'SEARCH-1',
            'barcode' => '500000000002',
            'sale_price' => 45,
            'wholesale_price' => 35,
        ]);
        ProductItem::factory()->create([
            'company_id' => $company->id,
            'name' => 'Different Product',
        ]);

        Livewire::test(ProductBrowser::class, [
            'selectedCompanyId' => $company->id,
            'selectedCustomerId' => $customer->id,
        ])
            ->call('setSearch', 'Searchable')
            ->assertSet('productOptions', fn (array $products): bool => count($products) === 1
                && $products[0]['id'] === $product->id
                && (float) $products[0]['sale_price'] === 35.0)
            ->call('setSearch', '500000000002')
            ->assertDispatched('pos-add-product', fn (string $event, array $params): bool => $event === 'pos-add-product'
                && $params['product']['id'] === $product->id
                && $params['product']['barcode'] === '500000000002'
                && (float) $params['product']['sale_price'] === 35.0);
    }

    public function test_scanned_barcode_only_adds_a_product_from_the_selected_company(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $this->actingAs(User::factory()->create(['company_id' => $company->id]));

        $product = ProductItem::factory()->create([
            'company_id' => $company->id,
            'name' => 'Company Product',
            'barcode' => '500000000003',
        ]);
        ProductItem::factory()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Other Company Product',
            'barcode' => '500000000003',
        ]);

        Livewire::test(ProductBrowser::class, ['selectedCompanyId' => $company->id])
            ->call('scanBarcode', '500000000003')
            ->assertDispatched('pos-add-product', fn (string $event, array $params): bool => $event === 'pos-add-product'
                && $params['product']['id'] === $product->id);
    }
}
