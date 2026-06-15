<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Status;
use App\Enums\UserRole;
use App\Filament\Pages\PosSales;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\ProductItem;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Models\Variation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_uses_custom_flux_layout(): void
    {
        $company = Company::factory()->create();

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'status' => Status::Active,
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Business overview')
            ->assertSee('Total Sales')
            ->assertDontSee('FilamentInfoWidget')
            ->assertDontSee('AccountWidget');
    }

    public function test_admin_media_page_loads_with_curator_table(): void
    {
        $company = Company::factory()->create();

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'status' => Status::Active,
        ]);

        $this->actingAs($user)
            ->get('/admin/media')
            ->assertOk()
            ->assertSee('Media');
    }

    public function test_company_level_master_pages_load(): void
    {
        $company = Company::factory()->create();

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'status' => Status::Active,
        ]);

        $this->actingAs($user)->get('/admin/customers')->assertOk()->assertSee('Customers');
        $this->actingAs($user)->get('/admin/suppliers')->assertOk()->assertSee('Suppliers');
        $this->actingAs($user)->get('/admin/categories')->assertOk()->assertSee('Categories');
        $this->actingAs($user)->get('/admin/brands')->assertOk()->assertSee('Brands');
        $this->actingAs($user)->get('/admin/items')->assertOk()->assertSee('Product Items');
        $this->actingAs($user)->get('/admin/variations')->assertOk()->assertSee('Variations');
        $this->actingAs($user)->get('/admin/pos-sales')->assertOk()->assertSee('POS Sales');
    }

    public function test_variations_page_shows_variation_types(): void
    {
        $company = Company::factory()->create();

        $variation = Variation::query()->create([
            'company_id' => $company->id,
            'name' => 'Colour',
        ]);

        $variation->types()->createMany([
            ['name' => 'White'],
            ['name' => 'Black'],
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'status' => Status::Active,
        ]);

        $this->actingAs($user)
            ->get('/admin/variations')
            ->assertOk()
            ->assertSee('Colour')
            ->assertSee('White')
            ->assertSee('Black');
    }

    public function test_pos_sales_page_shows_company_products(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create([
            'company_id' => $company->id,
            'name' => 'Body Spray',
            'status' => Status::Active,
        ]);
        $brand = Brand::factory()->create([
            'company_id' => $company->id,
            'name' => 'Afnan',
            'status' => Status::Active,
        ]);

        ProductItem::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'item_code' => 'BARCODE-001',
            'name' => 'Test POS Product',
            'opening_stock' => 0,
            'status' => Status::Active,
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'status' => Status::Active,
        ]);

        $this->actingAs($user)
            ->get('/admin/pos-sales')
            ->assertOk()
            ->assertSee('Test POS Product')
            ->assertSee('BARCODE-001')
            ->assertSee('Body Spray')
            ->assertSee('Afnan');
    }

    public function test_pos_payment_modal_uses_enabled_payment_methods(): void
    {
        $company = Company::factory()->create();

        $product = ProductItem::factory()->create([
            'company_id' => $company->id,
            'name' => 'Paid Product',
            'sale_price' => 18,
            'status' => Status::Active,
        ]);

        PaymentMethod::query()->create([
            'company_id' => $company->id,
            'name' => 'Cash',
            'is_enabled' => true,
        ]);

        PaymentMethod::query()->create([
            'company_id' => $company->id,
            'name' => 'Disabled Method',
            'is_enabled' => false,
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'status' => Status::Active,
        ]);

        $this->actingAs($user);

        Livewire::test(PosSales::class)
            ->call('addProduct', $product->id)
            ->call('payNow')
            ->assertSet('showPaymentModal', true)
            ->assertSet('paymentStatus', 'paid')
            ->assertSee('Make Payment')
            ->assertSee('Cash')
            ->assertDontSee('Disabled Method');
    }

    public function test_pos_customer_picker_is_company_scoped_and_selects_created_customer(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        Customer::factory()->create([
            'company_id' => $company->id,
            'name' => 'Local Customer',
            'status' => Status::Active,
        ]);

        Customer::factory()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Other Customer',
            'status' => Status::Active,
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'status' => Status::Active,
        ]);

        $this->actingAs($user);

        Livewire::test(PosSales::class)
            ->assertSee('Local Customer')
            ->assertDontSee('Other Customer')
            ->call('openCustomerModal')
            ->set('customerName', 'Counter Sale Customer')
            ->set('customerPhone', '01234567890')
            ->call('saveCustomer')
            ->assertSet('showCustomerModal', false)
            ->assertSet('selectedCustomerId', Customer::query()->where('name', 'Counter Sale Customer')->value('id'))
            ->assertSee('Counter Sale Customer');

        $this->assertDatabaseHas('customers', [
            'company_id' => $company->id,
            'name' => 'Counter Sale Customer',
            'phone' => '01234567890',
        ]);
    }

    public function test_pos_submit_payment_creates_sales_invoice_for_selected_customer(): void
    {
        $company = Company::factory()->create();
        $customer = Customer::factory()->create([
            'company_id' => $company->id,
            'name' => 'Invoice Customer',
            'status' => Status::Active,
        ]);
        $product = ProductItem::factory()->create([
            'company_id' => $company->id,
            'name' => 'Invoice Product',
            'sale_price' => 20,
            'status' => Status::Active,
        ]);
        $paymentMethod = PaymentMethod::query()->create([
            'company_id' => $company->id,
            'name' => 'Cash',
            'is_enabled' => true,
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'status' => Status::Active,
        ]);

        $this->actingAs($user);

        Livewire::test(PosSales::class)
            ->set('selectedCustomerId', $customer->id)
            ->set('paymentMethodId', $paymentMethod->id)
            ->call('addProduct', $product->id)
            ->call('submitPayment', false)
            ->assertSet('showPaymentModal', false)
            ->assertSet('cart', []);

        $invoice = SalesInvoice::query()->first();

        $this->assertNotNull($invoice);
        $this->assertSame($company->id, $invoice->company_id);
        $this->assertSame($customer->id, $invoice->customer_id);
        $this->assertSame($paymentMethod->id, $invoice->payment_method_id);
        $this->assertSame('paid', $invoice->status->value);
        $this->assertDatabaseHas('sales_invoice_items', [
            'invoice_id' => $invoice->id,
            'product_item_id' => $product->id,
            'description' => 'Invoice Product',
        ]);
    }

    public function test_pos_submit_and_print_redirects_to_printable_invoice(): void
    {
        $company = Company::factory()->create();
        $customer = Customer::factory()->create([
            'company_id' => $company->id,
            'status' => Status::Active,
        ]);
        $product = ProductItem::factory()->create([
            'company_id' => $company->id,
            'sale_price' => 10,
            'status' => Status::Active,
        ]);
        $paymentMethod = PaymentMethod::query()->create([
            'company_id' => $company->id,
            'name' => 'Cash',
            'is_enabled' => true,
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'status' => Status::Active,
        ]);

        $this->actingAs($user);

        Livewire::test(PosSales::class)
            ->set('selectedCustomerId', $customer->id)
            ->set('paymentMethodId', $paymentMethod->id)
            ->call('addProduct', $product->id)
            ->call('submitPayment', true)
            ->assertRedirect(route('pos.sales-invoices.print', SalesInvoice::query()->first()));

        $this->get(route('pos.sales-invoices.print', SalesInvoice::query()->first()))
            ->assertOk()
            ->assertSee('Invoice')
            ->assertSee(SalesInvoice::query()->first()->invoice_no);
    }

    public function test_pos_recent_sales_show_customer_payment_metadata_and_print_action(): void
    {
        $company = Company::factory()->create();
        $customer = Customer::factory()->create([
            'company_id' => $company->id,
            'name' => 'Recent Customer',
            'status' => Status::Active,
        ]);
        $product = ProductItem::factory()->create([
            'company_id' => $company->id,
            'sale_price' => 10,
            'status' => Status::Active,
        ]);
        $paymentMethod = PaymentMethod::query()->create([
            'company_id' => $company->id,
            'name' => 'Card',
            'is_enabled' => true,
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'status' => Status::Active,
        ]);

        $this->actingAs($user);

        Livewire::test(PosSales::class)
            ->set('selectedCustomerId', $customer->id)
            ->set('paymentMethodId', $paymentMethod->id)
            ->set('paymentStatus', 'partial')
            ->call('addProduct', $product->id)
            ->call('submitPayment', false)
            ->call('openQuickModal', 'recent-sales')
            ->assertSee('Date')
            ->assertSee('Reference')
            ->assertSee('Customer')
            ->assertSee('Grand Total')
            ->assertSee('Payment Status')
            ->assertSee('Payment Type')
            ->assertSee('Action')
            ->assertSee('Recent Customer')
            ->assertSee('Partial')
            ->assertSee('Card')
            ->assertSee(route('pos.sales-invoices.print', SalesInvoice::query()->first()));
    }

    public function test_pos_cart_price_override_updates_totals(): void
    {
        $company = Company::factory()->create();

        $product = ProductItem::factory()->create([
            'company_id' => $company->id,
            'sale_price' => 18,
            'status' => Status::Active,
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'status' => Status::Active,
        ]);

        $this->actingAs($user);

        Livewire::test(PosSales::class)
            ->call('addProduct', $product->id)
            ->set("cart.{$product->id}.price", '12.50')
            ->assertSee('£12.50');
    }
}
