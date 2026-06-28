<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\Reports\BalanceSheetService;
use App\Services\Reports\ReportDateRangeService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use UnitEnum;

class BalanceSheetReportPage extends Page
{
    protected static ?string $title = 'Balance Sheet';

    protected static ?string $navigationLabel = 'Balance Sheet';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 13;

    protected static ?string $slug = 'reports/balance-sheet';

    protected string $view = 'filament.pages.balance-sheet-report-page';

    protected Width|string|null $maxContentWidth = Width::Full;

    public string $financialYear = '';

    public string $asOnDate = '';

    public string $dateRange = 'today';

    public ?string $customStartDate = null;

    public ?string $customEndDate = null;

    public bool $showZeroBalances = false;

    /**
     * @var array<int, string>
     */
    public array $expandedGroups = [];

    public function mount(): void
    {
        $today = now();
        $this->asOnDate = $this->balanceSheetAsOnDate();
        $this->financialYear = $this->financialYearFor($today);
    }

    public function getReport(): array
    {
        return app(BalanceSheetService::class)->report(
            companyId: null,
            asOnDate: $this->balanceSheetAsOnDate(),
            showZeroBalances: $this->showZeroBalances,
        );
    }

    public function dateRangeOptions(): array
    {
        return app(ReportDateRangeService::class)->options();
    }

    public function resolvedDateRange(): array
    {
        return app(ReportDateRangeService::class)->resolve(
            $this->dateRange,
            $this->customStartDate,
            $this->customEndDate,
        );
    }

    public function balanceSheetAsOnDate(): string
    {
        return $this->resolvedDateRange()['end_date']->toDateString();
    }

    public function reportDateLabel(): string
    {
        return 'Balance Sheet as at '.$this->resolvedDateRange()['end_date']->format('d-M-Y');
    }

    public function reportDateSlug(): string
    {
        return 'as-at-'.$this->resolvedDateRange()['end_date']->format('Y-m-d');
    }

    public function updatedDateRange(): void
    {
        if ($this->dateRange !== 'custom') {
            $this->customStartDate = null;
            $this->customEndDate = null;
        } else {
            $this->customStartDate ??= now()->toDateString();
            $this->customEndDate ??= now()->toDateString();
        }

        $this->asOnDate = $this->balanceSheetAsOnDate();
    }

    public function applyFilters(): void
    {
        $this->asOnDate = $this->balanceSheetAsOnDate();
    }

    public function resetFilters(): void
    {
        $this->dateRange = 'today';
        $this->customStartDate = null;
        $this->customEndDate = null;
        $this->showZeroBalances = false;
        $this->asOnDate = $this->balanceSheetAsOnDate();
    }

    public function expandAll(): void
    {
        $groups = collect($this->getReport()['sections'])
            ->flatMap(fn (array $section): array => collect($section['groups'])
                ->flatMap(fn (array $group): array => [
                    $group['key'],
                    ...collect($group['categories'])->pluck('key')->all(),
                ])
                ->all())
            ->values()
            ->all();

        $this->expandedGroups = $groups;
    }

    public function collapseAll(): void
    {
        $this->expandedGroups = [];
    }

    public function toggleGroup(string $key): void
    {
        if (in_array($key, $this->expandedGroups, true)) {
            $this->expandedGroups = array_values(array_diff($this->expandedGroups, [$key]));

            return;
        }

        $this->expandedGroups[] = $key;
    }

    public function isExpanded(string $key): bool
    {
        return in_array($key, $this->expandedGroups, true);
    }

    public function exportUrl(string $format): string
    {
        return route('reports.balance-sheet.export', [
            'format' => $format,
            'date_range' => $this->dateRange,
            'custom_start_date' => $this->customStartDate,
            'custom_end_date' => $this->customEndDate,
            'as_on_date' => $this->balanceSheetAsOnDate(),
            'show_zero' => $this->showZeroBalances ? 1 : 0,
        ]);
    }

    public function printUrl(): string
    {
        return route('reports.balance-sheet.print', [
            'date_range' => $this->dateRange,
            'custom_start_date' => $this->customStartDate,
            'custom_end_date' => $this->customEndDate,
            'as_on_date' => $this->balanceSheetAsOnDate(),
            'show_zero' => $this->showZeroBalances ? 1 : 0,
        ]);
    }

    private function financialYearFor(Carbon $date): string
    {
        $startYear = $date->month >= 4 ? $date->year : $date->year - 1;

        return $startYear.'-'.($startYear + 1);
    }
}
