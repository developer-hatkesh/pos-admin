<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use Awcodes\Curator\CuratorPlugin;
use App\Filament\Pages\PosSales;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Settings;
use App\Services\Settings\AppSettings;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\HtmlString;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->passwordReset()
            ->profile()
            ->brandName(fn (): string => AppSettings::storeBrandName())
            ->brandLogo(fn (): ?string => AppSettings::storeLogoUrl())
            ->brandLogoHeight('2.25rem')
            ->sidebarWidth('18rem')
            ->collapsedSidebarWidth('5rem')
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            ->topNavigation(false)
            ->userMenu()
            ->navigationGroups([
                'Sales',
                'Income',
                'Purchases',
                'Inventory',
                'Contacts',
                'Vouchers',
                'Accounting',
                'Expenses',
                'Reports',
                'Settings',
                'System',
            ])
            ->darkMode()
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn (): HtmlString => new HtmlString(
                    '<a href="'.e(PosSales::getUrl()).'" class="flux-pos-topbar-btn" aria-label="Open POS sales">POS</a>'
                )
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_LOGO_AFTER,
                fn (): HtmlString => new HtmlString(view('filament.partials.company-switcher')->render())
            )
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->colors([
                'primary' => [
                    50 => '#eff6ff',
                    100 => '#dbeafe',
                    200 => '#bfdbfe',
                    300 => '#93c5fd',
                    400 => '#3b82f6',
                    500 => '#1e40af',
                    600 => '#1e3a8a',
                    700 => '#172554',
                    800 => '#0f1f45',
                    900 => '#08152f',
                    950 => '#050b1a',
                ],
                'gray' => Color::Slate,
                'info' => Color::Violet,
                'success' => Color::Green,
                'warning' => Color::Amber,
                'danger' => Color::Red,
            ])
            ->plugins([
                CuratorPlugin::make()
                    ->navigationGroup('Settings')
                    ->navigationSort(3),
                FilamentShieldPlugin::make()
                    ->navigationGroup('System')
                    ->navigationSort(3),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->pages([
                Dashboard::class,
                PosSales::class,
                Settings::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                'throttle:admin',
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
