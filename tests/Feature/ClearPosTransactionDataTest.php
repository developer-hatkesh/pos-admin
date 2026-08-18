<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ProductItem;
use App\Models\SalesInvoice;
use App\Models\Variation;
use App\Models\VariationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ClearPosTransactionDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_is_company_scoped_and_restarts_visible_document_number(): void
    {
        $target = Company::factory()->create(['name' => 'Demo 5']);
        $other = Company::factory()->create(['name' => 'Other Company']);
        $targetCustomer = Customer::factory()->create(['company_id' => $target->id]);
        $otherCustomer = Customer::factory()->create(['company_id' => $other->id]);
        $targetInvoice = $this->invoice($target->id, $targetCustomer->id, 'SI-001');
        $otherInvoice = $this->invoice($other->id, $otherCustomer->id, 'SI-001');
        $targetCategory = Category::factory()->create(['company_id' => $target->id]);
        $otherCategory = Category::factory()->create(['company_id' => $other->id]);
        $targetBrand = Brand::factory()->create(['company_id' => $target->id]);
        $otherBrand = Brand::factory()->create(['company_id' => $other->id]);
        $targetVariation = Variation::query()->create(['company_id' => $target->id, 'name' => 'Target Attribute']);
        $otherVariation = Variation::query()->create(['company_id' => $other->id, 'name' => 'Other Attribute']);
        $targetVariationType = VariationType::query()->create(['variation_id' => $targetVariation->id, 'name' => 'Target Value']);
        $otherVariationType = VariationType::query()->create(['variation_id' => $otherVariation->id, 'name' => 'Other Value']);
        $targetProduct = ProductItem::factory()->create([
            'company_id' => $target->id,
            'category_id' => $targetCategory->id,
            'brand_id' => $targetBrand->id,
            'opening_stock' => 12,
            'current_stock' => 4,
        ]);
        $otherProduct = ProductItem::factory()->create([
            'company_id' => $other->id,
            'category_id' => $otherCategory->id,
            'brand_id' => $otherBrand->id,
            'opening_stock' => 22,
            'current_stock' => 9,
        ]);

        $targetInvoice->items()->create([
            'product_item_id' => $targetProduct->id,
            'description' => 'Target item',
            'qty' => 1,
            'rate' => 10,
            'vat_rate' => 0,
            'vat_amount' => 0,
            'line_total' => 10,
        ]);
        $otherInvoice->items()->create([
            'product_item_id' => $otherProduct->id,
            'description' => 'Other item',
            'qty' => 1,
            'rate' => 10,
            'vat_rate' => 0,
            'vat_amount' => 0,
            'line_total' => 10,
        ]);

        $exitCode = Artisan::call('transactions:clear-pos-data', [
            '--company' => $target->id,
            '--force' => true,
            '--keep-bank-accounts' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseMissing('sales_invoices', ['id' => $targetInvoice->id]);
        $this->assertDatabaseMissing('customers', ['id' => $targetCustomer->id]);
        $this->assertDatabaseHas('sales_invoices', ['id' => $otherInvoice->id]);
        $this->assertDatabaseHas('sales_invoice_items', ['invoice_id' => $otherInvoice->id]);
        $this->assertDatabaseHas('customers', ['id' => $otherCustomer->id]);
        $this->assertDatabaseMissing('product_items', ['id' => $targetProduct->id]);
        $this->assertDatabaseMissing('categories', ['id' => $targetCategory->id]);
        $this->assertDatabaseMissing('brands', ['id' => $targetBrand->id]);
        $this->assertDatabaseMissing('variations', ['id' => $targetVariation->id]);
        $this->assertDatabaseMissing('variation_types', ['id' => $targetVariationType->id]);
        $this->assertSame(9.0, (float) $otherProduct->refresh()->current_stock);
        $this->assertDatabaseHas('categories', ['id' => $otherCategory->id]);
        $this->assertDatabaseHas('brands', ['id' => $otherBrand->id]);
        $this->assertDatabaseHas('variations', ['id' => $otherVariation->id]);
        $this->assertDatabaseHas('variation_types', ['id' => $otherVariationType->id]);
        $this->assertSame('SI-001', SalesInvoice::nextInvoiceNo($target->id));
        $this->assertSame('SI-002', SalesInvoice::nextInvoiceNo($other->id));
    }

    public function test_dry_run_and_missing_company_never_delete_data(): void
    {
        $company = Company::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $company->id]);
        $invoice = $this->invoice($company->id, $customer->id, 'SI-001');

        $this->assertSame(0, Artisan::call('transactions:clear-pos-data', [
            '--company' => $company->id,
            '--dry-run' => true,
        ]));
        $this->assertDatabaseHas('sales_invoices', ['id' => $invoice->id]);

        $this->assertSame(1, Artisan::call('transactions:clear-pos-data', ['--force' => true]));
        $this->assertDatabaseHas('sales_invoices', ['id' => $invoice->id]);
    }

    private function invoice(int $companyId, int $customerId, string $number): SalesInvoice
    {
        return SalesInvoice::query()->create([
            'company_id' => $companyId,
            'customer_id' => $customerId,
            'invoice_no' => $number,
            'invoice_date' => '2026-08-18',
            'subtotal' => 10,
            'discount' => 0,
            'vat_total' => 0,
            'total' => 10,
            'status' => InvoiceStatus::Posted,
        ]);
    }
}
