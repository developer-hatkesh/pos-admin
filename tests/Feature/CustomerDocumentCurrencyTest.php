<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Party;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Support\CurrencyFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerDocumentCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_documents_snapshot_the_customer_currency_without_conversion(): void
    {
        $customer = Customer::factory()->create(['currency_id' => 'EUR']);
        $party = Party::factory()->create(['company_id' => $customer->company_id]);

        $invoice = SalesInvoice::create([
            'company_id' => $customer->company_id,
            'invoice_no' => 'SI-CURRENCY-1',
            'party_id' => $party->id,
            'customer_id' => $customer->id,
            'invoice_date' => today(),
            'total' => 123.45,
        ]);

        $return = SalesReturn::create([
            'company_id' => $customer->company_id,
            'return_no' => 'CN-CURRENCY-1',
            'sales_invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'return_date' => today(),
            'total' => 23.45,
        ]);

        $this->assertSame('EUR', $invoice->currency_id);
        $this->assertSame('EUR', $return->currency_id);
        $this->assertSame("\u{20AC} 123.45", CurrencyFormatter::formatForCurrency($invoice->total, $invoice->currency_id));
        $this->assertSame(123.45, (float) $invoice->total);
    }
}
