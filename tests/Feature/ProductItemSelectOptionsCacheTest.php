<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ProductItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductItemSelectOptionsCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_select_options_are_scoped_by_company_and_invalidated_when_products_change(): void
    {
        config()->set('cache.default', 'array');

        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $product = ProductItem::factory()->create([
            'company_id' => $company->id,
            'name' => 'Cached Product',
            'item_code' => 'CACHE-1',
        ]);
        ProductItem::factory()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Other Product',
            'item_code' => 'OTHER-1',
        ]);

        $this->assertSame(
            [$product->id => 'Cached Product (CACHE-1)'],
            ProductItem::cachedSelectOptions($company->id),
        );

        $product->update(['name' => 'Updated Product']);

        $this->assertSame(
            [$product->id => 'Updated Product (CACHE-1)'],
            ProductItem::cachedSelectOptions($company->id),
        );

        $product->delete();

        $this->assertSame([], ProductItem::cachedSelectOptions($company->id));
    }
}
