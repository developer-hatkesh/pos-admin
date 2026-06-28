<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\Concerns;

use App\Services\Reports\ReportDateRangeService;
use Illuminate\Support\Carbon;

trait HasPermanentLedgerReportFilters
{
    public string $dateRange = 'today';

    public ?string $customStartDate = null;

    public ?string $customEndDate = null;

    public string $balanceType = 'all';

    public string $status = 'all';

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

    public function reportStartDate(): string
    {
        return $this->resolvedDateRange()['start_date']->toDateString();
    }

    public function reportEndDate(): string
    {
        return $this->resolvedDateRange()['end_date']->toDateString();
    }

    public function reportDateLabel(): string
    {
        return $this->resolvedDateRange()['label'];
    }

    public function reportDateSlug(): string
    {
        return $this->resolvedDateRange()['slug'];
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

        $this->resetTablePage();
    }

    public function applyFilters(): void
    {
        $this->resetTablePage();
    }

    public function resetFilters(): void
    {
        $this->dateRange = 'today';
        $this->customStartDate = null;
        $this->customEndDate = null;
        $this->balanceType = 'all';
        $this->status = 'all';
        $this->tableSearch = '';

        $this->resetTablePage();
    }

    public function ledgerReportQueryParameters(array $extra = []): array
    {
        return [
            'date_range' => $this->dateRange,
            'custom_start_date' => $this->customStartDate,
            'custom_end_date' => $this->customEndDate,
            'start_date' => $this->reportStartDate(),
            'end_date' => $this->reportEndDate(),
            ...$extra,
        ];
    }

    public function exportUrl(string $routeName, string $format = 'csv'): string
    {
        return route($routeName, $this->ledgerReportQueryParameters(['format' => $format]));
    }

    public function printUrl(string $routeName, bool $pdf = false): string
    {
        return route($routeName, $this->ledgerReportQueryParameters($pdf ? ['pdf' => 1] : []));
    }

    private function resetTablePage(): void
    {
        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }
}
