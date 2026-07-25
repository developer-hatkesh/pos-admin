<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\DocumentTotals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentTotalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_discount_tax_is_rounded_once_per_vat_rate(): void
    {
        $result = DocumentTotals::calculate([
            'items' => [
                ['qty' => 1, 'rate' => 150, 'vat_rate' => 20],
                ['qty' => 1, 'rate' => 150, 'vat_rate' => 20],
                ['qty' => 1, 'rate' => 14400, 'vat_rate' => 20],
            ],
            'discount' => 100,
        ]);

        $this->assertSame(14700.0, $result['subtotal']);
        $this->assertSame(100.0, $result['discount']);
        $this->assertSame(2920.0, $result['vat_total']);
        $this->assertSame(17520.0, $result['total']);
        $this->assertSame($result['vat_total'], array_sum(array_column($result['items'], 'vat_amount')));
    }

    public function test_mixed_vat_rates_are_rounded_by_rate_group(): void
    {
        $result = DocumentTotals::calculate([
            'items' => [
                'standard' => ['qty' => 1, 'rate' => 100, 'vat_rate' => 20],
                'reduced' => ['qty' => 1, 'rate' => 100, 'vat_rate' => 5],
                'zero' => ['qty' => 1, 'rate' => 100, 'vat_rate' => 0],
            ],
            'discount' => 30,
        ]);

        $this->assertSame(300.0, $result['subtotal']);
        $this->assertSame(22.5, $result['vat_total']);
        $this->assertSame(292.5, $result['total']);
        $this->assertSame($result['vat_total'], array_sum(array_column($result['items'], 'vat_amount')));
    }

    public function test_fractional_quantity_is_rounded_to_minor_units(): void
    {
        $result = DocumentTotals::calculate([
            'items' => [['qty' => '1.005', 'rate' => '10.00', 'vat_rate' => '20.00']],
            'discount' => 0,
        ]);

        $this->assertSame(10.05, $result['subtotal']);
        $this->assertSame(2.01, $result['vat_total']);
        $this->assertSame(12.06, $result['total']);
    }

    public function test_shipping_is_added_after_vat_and_is_not_discounted_or_taxed(): void
    {
        $result = DocumentTotals::calculate([
            'items' => [['qty' => 1, 'rate' => 100, 'vat_rate' => 20]],
            'discount' => 10,
            'shipping' => 25,
        ]);

        $this->assertSame(100.0, $result['subtotal']);
        $this->assertSame(10.0, $result['discount']);
        $this->assertSame(18.0, $result['vat_total']);
        $this->assertSame(25.0, $result['shipping']);
        $this->assertSame(133.0, $result['total']);
    }
}
