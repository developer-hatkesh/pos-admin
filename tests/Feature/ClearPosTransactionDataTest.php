<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ProductItem;
use App\Models\SalesInvoice;
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
        $targetProduct = ProductItem::factory()->create(['company_id' => $target->id, 'opening_stock' => 12, 'current_stock' => 4]);
        $otherProduct = ProductItem::factory()->create(['company_id' => $other->id, 'opening_stock' => 22, 'current_stock' => 9]);

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
            '--stock' => 100,
            '--keep-bank-accounts' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseMissing('sales_invoices', ['id' => $targetInvoice->id]);
        $this->assertDatabaseMissing('customers', ['id' => $targetCustomer->id]);
        $this->assertDatabaseHas('sales_invoices', ['id' => $otherInvoice->id]);
        $this->assertDatabaseHas('sales_invoice_items', ['invoice_id' => $otherInvoice->id]);
        $this->assertDatabaseHas('customers', ['id' => $otherCustomer->id]);
        $this->assertSame(100.0, (float) $targetProduct->refresh()->current_stock);
        $this->assertSame(9.0, (float) $otherProduct->refresh()->current_stock);
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
