<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ProductItem;
use App\Models\PurchaseInvoice;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseInvoiceReferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_voucher_and_supplier_invoice_numbers_are_stored_separately(): void
    {
        $company = Company::factory()->create();
        $invoice = PurchaseInvoice::query()->create([
            'company_id' => $company->id,
            'supplier_invoice_no' => 'HAL-45872',
            'invoice_date' => '2026-08-11',
            'total' => 10,
        ]);

        $this->assertSame('PI-001', $invoice->voucher_no);
        $this->assertSame('PI-001', $invoice->invoice_no);
        $this->assertSame('HAL-45872', $invoice->supplier_invoice_no);

        $invoice->update(['supplier_invoice_no' => 'HAL-45872-REV']);

        $this->assertSame('PI-001', $invoice->fresh()->voucher_no);
        $this->assertSame('HAL-45872-REV', $invoice->fresh()->supplier_invoice_no);
    }

    public function test_duplicate_purchase_voucher_numbers_are_rejected_per_company(): void
    {
        $company = Company::factory()->create();
        PurchaseInvoice::query()->create([
            'company_id' => $company->id,
            'voucher_no' => 'PI-007',
            'invoice_no' => 'PI-007',
            'supplier_invoice_no' => 'ONE',
            'invoice_date' => '2026-08-11',
            'total' => 10,
        ]);

        $this->expectException(QueryException::class);

        PurchaseInvoice::query()->create([
            'company_id' => $company->id,
            'voucher_no' => 'PI-007',
            'invoice_no' => 'LEGACY-DIFFERENT',
            'supplier_invoice_no' => 'TWO',
            'invoice_date' => '2026-08-11',
            'total' => 20,
        ]);
    }

    public function test_purchase_line_description_is_saved_independently_from_product_master(): void
    {
        $company = Company::factory()->create();
        $product = ProductItem::factory()->create([
            'company_id' => $company->id,
            'description' => 'Master description',
        ]);
        $invoice = PurchaseInvoice::query()->create([
            'company_id' => $company->id,
            'supplier_invoice_no' => 'SUP-100',
            'invoice_date' => '2026-08-11',
            'total' => 12,
        ]);
        $line = $invoice->items()->create([
            'product_item_id' => $product->id,
            'description' => 'Invoice-specific description',
            'qty' => 1,
            'rate' => 10,
            'vat_rate' => 20,
            'vat_amount' => 2,
            'line_total' => 12,
        ]);

        $this->assertSame('Invoice-specific description', $line->fresh()->description);
        $this->assertSame('Master description', $product->fresh()->description);
    }
}
