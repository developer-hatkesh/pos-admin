<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Status;
use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\ProductItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->actingAs($user)->get('/admin/pos-sales')->assertOk()->assertSee('POS Sales');
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
}
