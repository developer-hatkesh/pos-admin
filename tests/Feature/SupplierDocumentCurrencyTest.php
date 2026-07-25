<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Party;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Support\CurrencyFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierDocumentCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_documents_snapshot_the_supplier_currency_without_conversion(): void
    {
        $supplier = Supplier::factory()->create(['currency_id' => 'USD']);
        $party = Party::factory()->create(['company_id' => $supplier->company_id]);

        $invoice = PurchaseInvoice::create([
            'company_id' => $supplier->company_id,
            'invoice_no' => 'PI-CURRENCY-1',
            'party_id' => $party->id,
            'supplier_id' => $supplier->id,
            'invoice_date' => today(),
            'total' => 123.45,
        ]);

        $return = PurchaseReturn::create([
            'company_id' => $supplier->company_id,
            'return_no' => 'PR-CURRENCY-1',
            'purchase_invoice_id' => $invoice->id,
            'supplier_id' => $supplier->id,
            'return_date' => today(),
            'total' => 23.45,
        ]);

        $this->assertSame('USD', $invoice->currency_id);
        $this->assertSame('USD', $return->currency_id);
        $this->assertSame('$ 123.45', CurrencyFormatter::formatForCurrency($invoice->total, $invoice->currency_id));
        $this->assertSame(123.45, (float) $invoice->total);
    }
}
