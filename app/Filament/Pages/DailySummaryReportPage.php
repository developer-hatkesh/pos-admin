<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\Reports\DailySummaryReportService;
use App\Services\Reports\ReportDateRangeService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class DailySummaryReportPage extends Page
{
    protected static ?string $title = 'Summary Report';

    protected static ?string $navigationLabel = 'Summary Report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'reports/summary';

    protected string $view = 'filament.pages.daily-summary-report-page';

    protected Width|string|null $maxContentWidth = Width::Full;

    public string $dateRange = 'today';

    public ?string $customStartDate = null;

    public ?string $customEndDate = null;

    public function getReport(): array
    {
        $range = $this->resolvedDateRange();

        return app(DailySummaryReportService::class)->report(
            companyId: null,
            startDate: $range['start_date'],
            endDate: $range['end_date'],
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

    public function updatedDateRange(): void
    {
        if ($this->dateRange !== 'custom') {
            $this->customStartDate = null;
            $this->customEndDate = null;

            return;
        }

        $this->customStartDate ??= now()->toDateString();
        $this->customEndDate ??= now()->toDateString();
    }

    public function applyFilters(): void
    {
        $this->resolvedDateRange();
    }

    public function resetFilters(): void
    {
        $this->dateRange = 'today';
        $this->customStartDate = null;
        $this->customEndDate = null;
    }

    public function reportDateLabel(): string
    {
        $range = $this->resolvedDateRange();

        return 'Showing: '.$range['label'].' ('.$range['start_date']->format('d-M-Y').' to '.$range['end_date']->format('d-M-Y').')';
    }

    public function exportUrl(string $format): string
    {
        return route('reports.summary.export', [
            'format' => $format,
            'date_range' => $this->dateRange,
            'custom_start_date' => $this->customStartDate,
            'custom_end_date' => $this->customEndDate,
        ]);
    }

    public function printUrl(): string
    {
        return route('reports.summary.print', [
            'date_range' => $this->dateRange,
            'custom_start_date' => $this->customStartDate,
            'custom_end_date' => $this->customEndDate,
        ]);
    }
}
