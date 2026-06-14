<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use Awcodes\Curator\CuratorPlugin;
use App\Filament\Pages\PosSales;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Settings;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
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
            ->brandName('Perfume POS')
            ->sidebarWidth('18rem')
            ->collapsedSidebarWidth('5rem')
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            ->topNavigation(false)
            ->userMenu()
            ->navigationGroups([
                'Dashboard',
                'Catalogue',
                'POS / Sales',
                'Purchasing',
                'Accounting',
                'Inventory',
                'System',
            ])
            ->darkMode()
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->colors([
                'primary' => Color::Blue,
                'gray' => Color::Slate,
                'info' => Color::Violet,
                'success' => Color::Green,
                'warning' => Color::Amber,
                'danger' => Color::Red,
            ])
            ->plugins([
                CuratorPlugin::make(),
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
