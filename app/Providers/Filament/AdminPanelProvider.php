<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use Awcodes\Curator\CuratorPlugin;
use App\Filament\Pages\BalanceSheetReportPage;
use App\Filament\Pages\DailySummaryReportPage;
use App\Filament\Pages\PosSales;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\SalesPurchaseCharts;
use App\Filament\Pages\Settings;
use App\Filament\Pages\StockAlerts;
use App\Filament\Pages\TodaysSummary;
use App\Filament\Pages\VatReport;
use App\Filament\Resources\Companies\CompanyResource;
use App\Http\Middleware\SetPermissionCompany;
use App\Http\Middleware\RestrictPlatformSuperAdminAccess;
use App\Services\Settings\AppSettings;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
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
            ->navigation(fn (): NavigationBuilder|bool => auth()->user()?->hasSuperAdminRole() === true
                ? app(NavigationBuilder::class)
                    ->group('Settings', CompanyResource::getNavigationItems(), collapsible: false)
                : true)
            ->navigationGroups([
                'Dashboard',
                'Sales',
                'Purchases',
                'Inventory',
                'Contacts',
                'Voucher',
                'Accounts',
                'Reports',
                'Settings',
                'Administration',
            ])
            ->navigationItems([
                NavigationItem::make('Sales Reports')
                    ->group('Reports')
                    ->icon(Heroicon::OutlinedChartBarSquare)
                    ->sort(1),
                NavigationItem::make('Purchase Reports')
                    ->group('Reports')
                    ->icon(Heroicon::OutlinedDocumentChartBar)
                    ->sort(2),
                NavigationItem::make('Inventory Reports')
                    ->group('Reports')
                    ->icon(Heroicon::OutlinedArchiveBox)
                    ->sort(3),
                NavigationItem::make('Ledger Reports')
                    ->group('Reports')
                    ->icon(Heroicon::OutlinedBookOpen)
                    ->sort(4),
                NavigationItem::make('Outstanding Reports')
                    ->group('Reports')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->sort(5),
                NavigationItem::make('Cash & Bank Reports')
                    ->group('Reports')
                    ->icon(Heroicon::OutlinedBuildingLibrary)
                    ->sort(6),
                NavigationItem::make('Tax Reports')
                    ->group('Reports')
                    ->icon(Heroicon::OutlinedReceiptPercent)
                    ->sort(7),
                NavigationItem::make('Financial Reports')
                    ->group('Reports')
                    ->icon(Heroicon::OutlinedScale)
                    ->sort(8),
            ])
            ->darkMode()
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): HtmlString => new HtmlString(
                    view('filament.partials.company-switcher', ['placement' => 'topbar'])->render()
                    .(auth()->user()?->hasSuperAdminRole() === true ? '' : '<a href="'.e(PosSales::getUrl()).'" class="flux-pos-topbar-btn" aria-label="Open POS sales">POS</a>')
                )
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_LOGO_AFTER,
                fn (): HtmlString => new HtmlString(view('filament.partials.company-switcher', ['placement' => 'sidebar'])->render())
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
                    ->navigationSort(4),
                FilamentShieldPlugin::make()
                    ->centralApp()
                    ->navigationGroup('Administration')
                    ->navigationSort(2),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->pages([
                Dashboard::class,
                TodaysSummary::class,
                SalesPurchaseCharts::class,
                StockAlerts::class,
                PosSales::class,
                DailySummaryReportPage::class,
                BalanceSheetReportPage::class,
                VatReport::class,
                Settings::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                SetPermissionCompany::class,
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
                RestrictPlatformSuperAdminAccess::class,
            ]);
    }
}
