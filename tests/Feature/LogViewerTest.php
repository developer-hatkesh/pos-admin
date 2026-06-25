<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Status;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LogViewerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        File::delete([
            storage_path('logs/laravel-2099-01-01.log'),
            storage_path('logs/laravel-2099-01-02.log'),
        ]);

        parent::tearDown();
    }

    public function test_guest_is_redirected_from_logs(): void
    {
        $this->get('/logs')->assertRedirect();
    }

    public function test_non_admin_cannot_view_logs(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Sales,
            'status' => Status::Active,
        ]);

        $this->actingAs($user)
            ->get('/logs')
            ->assertForbidden();
    }

    public function test_admin_can_list_daily_logs_and_view_selected_file(): void
    {
        File::put(storage_path('logs/laravel-2099-01-01.log'), 'older daily log');
        File::put(storage_path('logs/laravel-2099-01-02.log'), 'newest daily log');

        $user = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => Status::Active,
        ]);

        $this->actingAs($user)
            ->get('/logs')
            ->assertOk()
            ->assertSee('Daily Logs')
            ->assertSee('laravel-2099-01-02.log')
            ->assertSee('laravel-2099-01-01.log')
            ->assertSee('newest daily log');

        $this->actingAs($user)
            ->get('/logs/laravel-2099-01-01.log')
            ->assertOk()
            ->assertSee('older daily log')
            ->assertDontSee('newest daily log');
    }
}
