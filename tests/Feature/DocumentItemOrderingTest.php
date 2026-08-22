<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentItemOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_items_persist_creation_updates_additions_and_reordering(): void
    {
        $company = Company::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $company->id]);
        $supplier = Supplier::factory()->create(['company_id' => $company->id]);

        $salesInvoice = SalesInvoice::query()->create([
            'company_id' => $company->id, 'customer_id' => $customer->id,
            'invoice_no' => 'SI-ORDER', 'invoice_date' => today(),
        ]);
        $salesInvoiceLine = $this->assertItemLifecycle($salesInvoice);

        $purchaseInvoice = PurchaseInvoice::query()->create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id,
            'voucher_no' => 'PI-ORDER', 'invoice_no' => 'PI-ORDER', 'invoice_date' => today(),
        ]);
        $purchaseInvoiceLine = $this->assertItemLifecycle($purchaseInvoice);

        $estimate = Estimate::query()->create([
            'company_id' => $company->id, 'customer_id' => $customer->id,
            'estimate_no' => 'EST-ORDER', 'estimate_date' => today(),
        ]);
        $this->assertItemLifecycle($estimate);

        $salesReturn = SalesReturn::query()->create([
            'company_id' => $company->id, 'customer_id' => $customer->id,
            'sales_invoice_id' => $salesInvoice->id, 'return_no' => 'CN-ORDER', 'return_date' => today(),
        ]);
        $this->assertItemLifecycle($salesReturn, ['sales_invoice_item_id' => $salesInvoiceLine->id]);

        $purchaseReturn = PurchaseReturn::query()->create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id,
            'purchase_invoice_id' => $purchaseInvoice->id, 'return_no' => 'PR-ORDER', 'return_date' => today(),
        ]);
        $this->assertItemLifecycle($purchaseReturn, ['purchase_invoice_item_id' => $purchaseInvoiceLine->id]);
    }

    private function assertItemLifecycle(Model $document, array $extra = []): Model
    {
        $base = ['qty' => 1, 'rate' => 1, 'vat_rate' => 0, 'vat_amount' => 0, 'line_total' => 1];
        $second = $document->items()->create([...$base, ...$extra, 'description' => 'Second', 'sort_order' => 20]);
        $first = $document->items()->create([...$base, ...$extra, 'description' => 'First', 'sort_order' => 10]);

        $this->assertSame(['First', 'Second'], $document->fresh()->items->pluck('description')->all());

        $first->update(['description' => 'First edited', 'sort_order' => 30]);
        $second->update(['sort_order' => 10]);
        $document->items()->create([...$base, ...$extra, 'description' => 'Third', 'sort_order' => 20]);

        $reloadedItems = $document->fresh()->items;
        $this->assertSame(['Second', 'Third', 'First edited'], $reloadedItems->pluck('description')->all());
        $this->assertSame([10, 20, 30], $reloadedItems->pluck('sort_order')->all());

        return $second;
    }
}
