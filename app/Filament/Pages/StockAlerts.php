<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class StockAlerts extends Dashboard
{
    protected static string $routePath = '/dashboard/stock-alerts';

    protected static ?string $slug = 'dashboard/stock-alerts';

    protected static ?string $title = 'Stock Alerts';

    protected static ?string $navigationLabel = 'Stock Alerts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|UnitEnum|null $navigationGroup = 'Dashboard';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.stock-alerts';

    public function getViewData(): array
    {
        return [
            'stockAlerts' => $this->stockAlerts(),
        ];
    }
}
