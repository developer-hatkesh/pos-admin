<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
use App\Support\CurrencyFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactDefaultCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_contacts_use_the_current_company_default_currency(): void
    {
        $company = Company::factory()->create();
        $this->actingAs(User::factory()->create(['company_id' => $company->id]));
        AppSetting::setValue('currency', ['currency_default' => 'INR']);

        $customer = Customer::query()->create([
            'company_id' => $company->id,
            'company_name' => 'Default Currency Customer',
        ]);
        $supplier = Supplier::query()->create([
            'company_id' => $company->id,
            'company_name' => 'Default Currency Supplier',
        ]);

        $this->assertSame('INR', CurrencyFormatter::defaultCurrencyCode());
        $this->assertSame('INR', $customer->currency_id);
        $this->assertSame('INR', $supplier->currency_id);
    }

    public function test_explicit_contact_currency_is_not_overwritten(): void
    {
        $company = Company::factory()->create();
        $this->actingAs(User::factory()->create(['company_id' => $company->id]));
        AppSetting::setValue('currency', ['currency_default' => 'AED']);

        $customer = Customer::query()->create([
            'company_id' => $company->id,
            'company_name' => 'Euro Customer',
            'currency_id' => 'EUR',
        ]);
        $supplier = Supplier::query()->create([
            'company_id' => $company->id,
            'company_name' => 'Dollar Supplier',
            'currency_id' => 'USD',
        ]);

        $this->assertSame('EUR', $customer->currency_id);
        $this->assertSame('USD', $supplier->currency_id);
    }

    public function test_default_currency_is_scoped_to_the_current_company(): void
    {
        $firstCompany = Company::factory()->create();
        $firstUser = User::factory()->create(['company_id' => $firstCompany->id]);
        $this->actingAs($firstUser);
        AppSetting::setValue('currency', ['currency_default' => 'EUR']);

        $secondCompany = Company::factory()->create();
        $secondUser = User::factory()->create(['company_id' => $secondCompany->id]);
        $this->actingAs($secondUser);
        AppSetting::setValue('currency', ['currency_default' => 'AED']);

        $secondCompanyCustomer = Customer::query()->create([
            'company_id' => $secondCompany->id,
            'company_name' => 'Second Company Customer',
        ]);

        $firstCompanySupplier = Supplier::query()->create([
            'company_id' => $firstCompany->id,
            'company_name' => 'First Company Supplier',
        ]);

        $this->assertSame('AED', $secondCompanyCustomer->currency_id);
        $this->assertSame('EUR', $firstCompanySupplier->currency_id);
    }
}
