<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\Status;
use App\Enums\UserRole;
use App\Filament\Resources\PurchaseInvoices\Pages\CreatePurchaseInvoice;
use App\Filament\Resources\PurchaseInvoices\Pages\EditPurchaseInvoice;
use App\Filament\Resources\SalesInvoices\Pages\CreateSalesInvoice;
use App\Filament\Resources\SalesInvoices\Pages\EditSalesInvoice;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ProductItem;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\Accounting\PurchasePostingService;
use App\Services\Accounting\SalesPostingService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use ReflectionMethod;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class BlankInvoiceDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_blank_sales_and_purchase_invoices_are_created_as_zero_value_drafts_and_can_be_posted_after_items_are_added(): void
    {
        $company = Company::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $company->id, 'status' => Status::Active]);
        $supplier = Supplier::factory()->create(['company_id' => $company->id, 'status' => Status::Active]);
        $product = ProductItem::factory()->create(['company_id' => $company->id, 'stock_enabled' => false]);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::SuperAdmin,
            'status' => Status::Active,
        ]);

        $this->actingAs($user);

        Livewire::test(CreateSalesInvoice::class)
            ->fillForm([
                'customer_id' => $customer->id,
                'invoice_date' => today()->toDateString(),
                'status' => InvoiceStatus::Posted->value,
                'items' => [],
                'discount' => 25,
                'shipping' => 10,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        Livewire::test(CreatePurchaseInvoice::class)
            ->fillForm([
                'supplier_id' => $supplier->id,
                'supplier_invoice_no' => 'BLANK-SUPPLIER-001',
                'invoice_date' => today()->toDateString(),
                'items' => [],
                'discount' => 25,
                'shipping' => 10,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $salesInvoice = SalesInvoice::query()->firstOrFail();
        $purchaseInvoice = PurchaseInvoice::query()->firstOrFail();

        $this->assertSame(InvoiceStatus::Draft, $salesInvoice->status, 'Sales invoice should remain draft.');
        $this->assertSame(InvoiceStatus::Draft, $purchaseInvoice->status, 'Purchase invoice should remain draft.');

        foreach ([$salesInvoice, $purchaseInvoice] as $invoice) {
            $this->assertSame(0.0, (float) $invoice->subtotal);
            $this->assertSame(0.0, (float) $invoice->vat_total);
            $this->assertSame(0.0, (float) $invoice->shipping);
            $this->assertSame(0.0, (float) $invoice->total);
            $this->assertNull($invoice->journal_id);
            $this->assertDatabaseCount($invoice->items()->getModel()->getTable(), 0);
        }

        $this->seed(ChartOfAccountsSeeder::class);
        $taxRateId = TaxRate::defaultId();
        $line = [
            'product_item_id' => $product->id,
            'description' => 'Added later',
            'qty' => 1,
            'rate' => 100,
            'tax_rate_id' => $taxRateId,
            'vat_rate' => TaxRate::rateFor($taxRateId),
            'vat_amount' => 20,
            'line_total' => 120,
            'sort_order' => 0,
        ];

        $this->assertBlankDraftCanBeSaved(EditSalesInvoice::class, $salesInvoice);
        $this->assertBlankDraftCanBeSaved(EditPurchaseInvoice::class, $purchaseInvoice);

        $salesInvoice->items()->create($line);
        $purchaseInvoice->items()->create($line);

        foreach ([$salesInvoice->fresh(), $purchaseInvoice->fresh()] as $invoice) {
            $this->assertSame(InvoiceStatus::Draft, $invoice->status);
            $this->assertNull($invoice->journal_id);
            $this->assertSame(1, $invoice->items()->count());
        }

        app(SalesPostingService::class)->post($salesInvoice->fresh());
        app(PurchasePostingService::class)->post($purchaseInvoice->fresh());

        foreach ([$salesInvoice->fresh(), $purchaseInvoice->fresh()] as $invoice) {
            $this->assertSame(InvoiceStatus::Posted, $invoice->status);
            $this->assertSame(120.0, (float) $invoice->total);
            $this->assertNotNull($invoice->journal_id);
        }
    }

    public function test_existing_item_based_create_flow_still_posts_sales_and_purchase_invoices(): void
    {
        $company = Company::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $company->id, 'status' => Status::Active]);
        $supplier = Supplier::factory()->create(['company_id' => $company->id, 'status' => Status::Active]);
        $product = ProductItem::factory()->create(['company_id' => $company->id, 'stock_enabled' => false]);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::SuperAdmin,
            'status' => Status::Active,
        ]);

        $this->actingAs($user);
        $this->seed(ChartOfAccountsSeeder::class);
        $taxRateId = TaxRate::defaultId();
        $item = [
            'product_item_id' => $product->id,
            'description' => 'Normal invoice item',
            'qty' => 1,
            'rate' => 100,
            'tax_rate_id' => $taxRateId,
            'vat_rate' => TaxRate::rateFor($taxRateId),
        ];

        Livewire::test(CreateSalesInvoice::class)
            ->fillForm([
                'customer_id' => $customer->id,
                'invoice_date' => today()->toDateString(),
                'status' => InvoiceStatus::Posted->value,
                'items' => [$item],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        Livewire::test(CreatePurchaseInvoice::class)
            ->fillForm([
                'supplier_id' => $supplier->id,
                'supplier_invoice_no' => 'NORMAL-SUPPLIER-001',
                'invoice_date' => today()->toDateString(),
                'items' => [$item],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        foreach ([SalesInvoice::query()->firstOrFail(), PurchaseInvoice::query()->firstOrFail()] as $invoice) {
            $this->assertSame(InvoiceStatus::Posted, $invoice->status);
            $this->assertSame(120.0, (float) $invoice->total);
            $this->assertNotNull($invoice->journal_id);
            $this->assertSame(1, $invoice->items()->count());
        }
    }

    private function assertBlankDraftCanBeSaved(string $pageClass, SalesInvoice|PurchaseInvoice $invoice): void
    {
        $page = app($pageClass);
        $page->record = $invoice;
        $method = new ReflectionMethod($page, 'mutateFormDataBeforeSave');
        $data = $method->invoke($page, [
            'items' => [],
            'status' => InvoiceStatus::Posted->value,
            'discount' => 25,
            'shipping' => 10,
        ]);

        $this->assertSame(InvoiceStatus::Draft->value, $data['status']);
        $this->assertSame(0.0, (float) $data['discount']);
        $this->assertSame(0.0, (float) $data['shipping']);
        $this->assertSame(0.0, (float) $data['total']);
    }
}
