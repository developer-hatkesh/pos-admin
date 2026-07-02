<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\Reports\ReportDateRangeService;
use App\Services\Reports\VatReportService;
use App\Support\CurrentCompany;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use UnitEnum;

class VatReport extends Page
{
    protected static ?string $title = 'VAT Report';

    protected static ?string $navigationLabel = 'VAT Report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 11;

    protected static ?string $slug = 'reports/vat-report';

    protected string $view = 'filament.pages.vat-report';

    protected Width|string|null $maxContentWidth = Width::Full;

    public string $dateRange = 'this_month';

    public ?string $customStartDate = null;

    public ?string $customEndDate = null;

    public string $vatQuarter = 'date_range';

    public ?int $customerId = null;

    public ?int $supplierId = null;

    public ?int $vatType = null;

    public function getReport(): array
    {
        return app(VatReportService::class)->report($this->reportFilters());
    }

    public function dateRangeOptions(): array
    {
        return app(ReportDateRangeService::class)->options();
    }

    public function vatQuarterOptions(): array
    {
        $year = now()->year;

        return [
            'date_range' => 'Use date range',
            "{$year}-q1" => "Q1 {$year} (Jan-Mar)",
            "{$year}-q2" => "Q2 {$year} (Apr-Jun)",
            "{$year}-q3" => "Q3 {$year} (Jul-Sep)",
            "{$year}-q4" => "Q4 {$year} (Oct-Dec)",
            ($year - 1).'-q4' => 'Q4 '.($year - 1).' (Oct-Dec)',
        ];
    }

    public function customerOptions(): array
    {
        $companyId = app(CurrentCompany::class)->id();

        return $companyId ? app(VatReportService::class)->customerOptions($companyId) : [];
    }

    public function supplierOptions(): array
    {
        $companyId = app(CurrentCompany::class)->id();

        return $companyId ? app(VatReportService::class)->supplierOptions($companyId) : [];
    }

    public function vatTypeOptions(): array
    {
        return app(VatReportService::class)->vatTypeOptions();
    }

    public function reportFilters(): array
    {
        $range = $this->resolvedPeriod();

        return [
            'company_id' => app(CurrentCompany::class)->id(),
            'start_date' => $range['start_date']->toDateString(),
            'end_date' => $range['end_date']->toDateString(),
            'date_range' => $this->dateRange,
            'vat_quarter' => $this->vatQuarter,
            'customer_id' => $this->customerId,
            'supplier_id' => $this->supplierId,
            'vat_type' => $this->vatType,
        ];
    }

    public function resolvedPeriod(): array
    {
        if ($this->vatQuarter !== 'date_range') {
            return $this->quarterRange($this->vatQuarter);
        }

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

        $this->customStartDate ??= now()->startOfMonth()->toDateString();
        $this->customEndDate ??= now()->toDateString();
    }

    public function applyFilters(): void
    {
        $this->resolvedPeriod();
    }

    public function resetFilters(): void
    {
        $this->dateRange = 'this_month';
        $this->customStartDate = null;
        $this->customEndDate = null;
        $this->vatQuarter = 'date_range';
        $this->customerId = null;
        $this->supplierId = null;
        $this->vatType = null;
    }

    public function reportDateLabel(): string
    {
        $range = $this->resolvedPeriod();

        return 'Showing: '.$range['label'].' ('.$range['start_date']->format('d-M-Y').' to '.$range['end_date']->format('d-M-Y').')';
    }

    public function exportUrl(string $format): string
    {
        return route('reports.vat-report.export', [...$this->queryParameters(), 'format' => $format]);
    }

    public function printUrl(bool $pdf = false): string
    {
        return route('reports.vat-report.print', $this->queryParameters($pdf ? ['pdf' => 1] : []));
    }

    private function queryParameters(array $extra = []): array
    {
        return [
            'date_range' => $this->dateRange,
            'custom_start_date' => $this->customStartDate,
            'custom_end_date' => $this->customEndDate,
            'vat_quarter' => $this->vatQuarter,
            'customer_id' => $this->customerId,
            'supplier_id' => $this->supplierId,
            'vat_type' => $this->vatType,
            ...$extra,
        ];
    }

    private function quarterRange(string $quarter): array
    {
        if (! preg_match('/^(?<year>\d{4})-q(?<quarter>[1-4])$/', $quarter, $matches)) {
            return app(ReportDateRangeService::class)->resolve($this->dateRange, $this->customStartDate, $this->customEndDate);
        }

        $year = (int) $matches['year'];
        $quarterNumber = (int) $matches['quarter'];
        $startMonth = (($quarterNumber - 1) * 3) + 1;
        $start = Carbon::create($year, $startMonth, 1)->startOfDay();
        $end = $start->copy()->addMonthsNoOverflow(2)->endOfMonth()->endOfDay();

        return [
            'start_date' => $start,
            'end_date' => $end,
            'label' => 'VAT Quarter Q'.$quarterNumber.' '.$year,
            'slug' => 'vat-q'.$quarterNumber.'-'.$year,
        ];
    }
}
