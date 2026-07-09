<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class SalesPurchaseCharts extends Dashboard
{
    protected static string $routePath = '/dashboard/sales-purchase-charts';

    protected static ?string $slug = 'dashboard/sales-purchase-charts';

    protected static ?string $title = null;

    protected static ?string $navigationLabel = 'Sales & Purchase Charts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Dashboard';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.sales-purchase-charts';

    public function getViewData(): array
    {
        return [
            'weeklySalesPurchases' => $this->weeklySalesPurchases(),
            'topProducts' => $this->topProductsForWeek(),
            'topCategories' => $this->topCategoriesForWeek(),
            'topCustomers' => $this->topCustomersForWeek(),
        ];
    }
}
