<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class TodaysSummary extends Dashboard
{
    protected static string $routePath = '/dashboard/todays-summary';

    protected static ?string $slug = 'dashboard/todays-summary';

    protected static ?string $title = "Today's Summary";

    protected static ?string $navigationLabel = "Today's Summary";

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Dashboard';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.todays-summary';

    public function getViewData(): array
    {
        return [
            'metrics' => $this->metrics(),
            'recentSales' => $this->recentSales(),
        ];
    }
}
