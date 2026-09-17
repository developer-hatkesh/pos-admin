<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PurchaseReturnStatus;
use App\Enums\SalesReturnStatus;
use App\Filament\Resources\PurchaseReturns\PurchaseReturnResource;
use App\Filament\Resources\SalesReturns\SalesReturnResource;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ProductItem;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnInvoiceSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_selecting_purchase_invoices_populates_grouped_remaining_items(): void
    {
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $product = ProductItem::factory()->create(['company_id' => $company->id]);
        $firstInvoice = $this->purchaseInvoice($company, $supplier, 'PI-001');
        $secondInvoice = $this->purchaseInvoice($company, $supplier, 'PI-002');
        $firstLine = $firstInvoice->items()->create($this->line($product, 3));
        $secondInvoice->items()->create($this->line($product, 2));
        $return = PurchaseReturn::query()->create([
            'company_id' => $company->id,
            'purchase_invoice_id' => $firstInvoice->id,
            'supplier_id' => $supplier->id,
            'return_date' => today(),
            'status' => PurchaseReturnStatus::Posted,
        ]);
        $return->items()->create([
            ...$this->line($product, 1),
            'purchase_invoice_item_id' => $firstLine->id,
        ]);

        $items = PurchaseReturnResource::itemsFromSelectedInvoices([$firstInvoice->id, $secondInvoice->id]);

        $this->assertCount(1, $items);
        $this->assertSame($firstLine->id, $items[0]['purchase_invoice_item_id']);
        $this->assertSame(4.0, (float) $items[0]['qty']);
    }

    public function test_selecting_sales_invoices_populates_grouped_credit_note_items(): void
    {
        $company = Company::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $company->id]);
        $product = ProductItem::factory()->create(['company_id' => $company->id]);
        $firstInvoice = $this->salesInvoice($company, $customer, 'SI-001');
        $secondInvoice = $this->salesInvoice($company, $customer, 'SI-002');
        $firstLine = $firstInvoice->items()->create($this->line($product, 3));
        $secondInvoice->items()->create($this->line($product, 2));
        $return = SalesReturn::query()->create([
            'company_id' => $company->id,
            'sales_invoice_id' => $firstInvoice->id,
            'customer_id' => $customer->id,
            'return_date' => today(),
            'status' => SalesReturnStatus::Posted,
        ]);
        $return->items()->create([
            ...$this->line($product, 1),
            'sales_invoice_item_id' => $firstLine->id,
        ]);

        $items = SalesReturnResource::itemsFromSelectedInvoices([$firstInvoice->id, $secondInvoice->id]);

        $this->assertCount(1, $items);
        $this->assertSame($firstLine->id, $items[0]['sales_invoice_item_id']);
        $this->assertSame(4.0, (float) $items[0]['qty']);
    }

    private function purchaseInvoice(Company $company, Supplier $supplier, string $number): PurchaseInvoice
    {
        return PurchaseInvoice::query()->create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'invoice_no' => $number,
            'voucher_no' => $number,
            'invoice_date' => today(),
            'status' => InvoiceStatus::Posted,
        ]);
    }

    private function salesInvoice(Company $company, Customer $customer, string $number): SalesInvoice
    {
        return SalesInvoice::query()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'invoice_no' => $number,
            'invoice_date' => today(),
            'status' => InvoiceStatus::Posted,
        ]);
    }

    private function line(ProductItem $product, float $qty): array
    {
        return [
            'product_item_id' => $product->id,
            'description' => $product->name,
            'qty' => $qty,
            'rate' => 10,
            'vat_rate' => 20,
            'vat_amount' => $qty * 2,
            'line_total' => $qty * 12,
        ];
    }
}
