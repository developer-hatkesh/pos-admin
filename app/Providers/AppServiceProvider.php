<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\SetPermissionCompany;
use App\Services\Settings\AppSettings;
use Filament\Forms\Components\RichEditor;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        AppSettings::applyMailSettings();
        Livewire::addPersistentMiddleware(SetPermissionCompany::class);

        RichEditor::configureUsing(function (RichEditor $component): void {
            $component->extraAttributes(['class' => 'rich-editor-min-three-rows'], merge: true);
        });

        Gate::before(static function ($user, string $ability): ?bool {
            if (! method_exists($user, 'isSuperAdmin') || ! $user->isSuperAdmin()) {
                return null;
            }

            if (method_exists($user, 'hasSuperAdminRole') && $user->hasSuperAdminRole()) {
                return self::platformSuperAdminPermissionResult($ability);
            }

            return true;
        });

        RateLimiter::for('api', static function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('login', static function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('admin', static function (Request $request): Limit {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }

    private static function platformSuperAdminPermissionResult(string $ability): ?bool
    {
        if (str_starts_with($ability, 'view_')) {
            return false;
        }

        if (! str_contains($ability, ':')) {
            return null;
        }

        $allowedSubjects = [
            'AccountCategory',
            'AccountClass',
            'ChartOfAccount',
            'Company',
        ];

        foreach ($allowedSubjects as $subject) {
            if (str_ends_with($ability, ':'.$subject)) {
                return true;
            }
        }

        return false;
    }
}
