<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Status;
use App\Enums\UserRole;
use App\Filament\Pages\PosSales;
use App\Livewire\Pos\Cart;
use App\Models\AppSetting;
use App\Models\Company;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\ProductItem;
use App\Models\SalesInvoice;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\Settings\AppSettings;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PosDefaultTaxTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_uses_company_default_tax_and_persists_it_on_every_invoice_item(): void
    {
        $company = Company::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $company->id, 'status' => Status::Active]);
        $products = ProductItem::factory()->count(2)->create([
            'company_id' => $company->id,
            'sale_price' => 10,
            'status' => Status::Active,
        ]);
        $paymentMethod = PaymentMethod::query()->create([
            'company_id' => $company->id,
            'name' => 'Cash',
            'is_enabled' => true,
        ]);
        $taxRate = TaxRate::query()->create(['name' => 'POS Reduced', 'rate' => 5]);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'status' => Status::Active,
        ]);

        $this->actingAs($user);
        AppSetting::setValue('pos', ['pos_default_tax_rate_id' => $taxRate->id]);

        Livewire::test(PosSales::class)
            ->assertSet('taxRateId', $taxRate->id)
            ->set('selectedCustomerId', $customer->id)
            ->set('paymentMethodId', $paymentMethod->id)
            ->call('addProduct', $products[0]->id)
            ->call('addProduct', $products[1]->id)
            ->call('submitPayment', false)
            ->assertSet('taxRateId', $taxRate->id);

        $invoice = SalesInvoice::query()->firstOrFail();

        $this->assertSame(2, $invoice->items()->count());
        $this->assertSame([$taxRate->id], $invoice->items()->pluck('tax_rate_id')->unique()->values()->all());
        $this->assertSame([5.0], $invoice->items()->get()->map(fn ($item): float => (float) $item->vat_rate)->unique()->values()->all());
        $this->assertSame(1.0, (float) $invoice->vat_total);
    }

    public function test_manual_override_is_kept_for_the_sale_and_reset_restores_company_default(): void
    {
        $company = Company::factory()->create();
        $configuredTax = TaxRate::query()->create(['name' => 'Configured POS Tax', 'rate' => 5]);
        $overrideTax = TaxRate::query()->create(['name' => 'Override POS Tax', 'rate' => 12]);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => UserRole::Admin,
            'status' => Status::Active,
        ]);

        $this->actingAs($user);
        AppSetting::setValue('pos', ['pos_default_tax_rate_id' => $configuredTax->id]);

        Livewire::test(Cart::class, ['selectedCompanyId' => $company->id])
            ->assertSet('taxRateId', $configuredTax->id)
            ->set('taxRateId', $overrideTax->id)
            ->assertSet('taxRateId', $overrideTax->id)
            ->call('resetCart')
            ->assertSet('taxRateId', $configuredTax->id);
    }

    public function test_invalid_setting_falls_back_to_existing_standard_tax(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user);
        AppSetting::setValue('pos', ['pos_default_tax_rate_id' => 999999]);

        $this->assertSame(TaxRate::defaultId(), AppSettings::posDefaultTaxRateId());
    }

    public function test_default_tax_setting_is_isolated_by_company(): void
    {
        $firstCompany = Company::factory()->create();
        $secondCompany = Company::factory()->create();
        $firstTax = TaxRate::query()->create(['name' => 'First Company Tax', 'rate' => 5]);
        $secondTax = TaxRate::query()->create(['name' => 'Second Company Tax', 'rate' => 12]);
        $firstUser = User::factory()->create(['company_id' => $firstCompany->id]);
        $secondUser = User::factory()->create(['company_id' => $secondCompany->id]);

        AppSetting::query()->create([
            'company_id' => $firstCompany->id,
            'key' => 'pos',
            'value' => ['pos_default_tax_rate_id' => $firstTax->id],
        ]);
        AppSetting::query()->create([
            'company_id' => $secondCompany->id,
            'key' => 'pos',
            'value' => ['pos_default_tax_rate_id' => $secondTax->id],
        ]);

        $this->actingAs($firstUser);
        $this->assertSame($firstTax->id, AppSettings::posDefaultTaxRateId());

        app(CurrentCompany::class)->clear();
        auth()->logout();
        $this->actingAs($secondUser);
        $this->assertSame($secondTax->id, AppSettings::posDefaultTaxRateId());
    }
}
