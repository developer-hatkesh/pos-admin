<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ProductItem;
use App\Models\TaxRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTaxDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_resolves_its_configured_tax_as_the_document_default(): void
    {
        $taxRate = TaxRate::query()->where('rate', 5)->firstOrFail();
        $product = ProductItem::factory()->create([
            'company_id' => Company::factory(),
            'tax_rate_id' => $taxRate->id,
            'vat_rate' => 5,
        ]);

        $this->assertSame($taxRate->id, $product->defaultTaxRateId());
        $this->assertSame(5.0, $product->defaultVatRate());
    }

    public function test_legacy_product_vat_rate_resolves_without_a_tax_rate_id(): void
    {
        $product = ProductItem::factory()->create([
            'company_id' => Company::factory(),
            'tax_rate_id' => null,
            'vat_rate' => 20,
        ]);

        $this->assertSame(20.0, $product->defaultVatRate());
        $this->assertSame(
            TaxRate::query()->where('rate', 20)->value('id'),
            $product->defaultTaxRateId(),
        );
    }
}
