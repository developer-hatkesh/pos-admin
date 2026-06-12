<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Status;
use App\Enums\UserRole;
use App\Models\Company;
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
}
