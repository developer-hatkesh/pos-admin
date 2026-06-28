<?php

declare(strict_types=1);

namespace App\Services\Reports;

use Illuminate\Support\Carbon;
use InvalidArgumentException;

class ReportDateRangeService
{
    public function resolve(string $range, ?string $customStartDate = null, ?string $customEndDate = null): array
    {
        $today = now();

        [$start, $end, $label] = match ($range) {
            'today' => [$today->copy()->startOfDay(), $today->copy()->endOfDay(), 'Today'],
            'yesterday' => [$today->copy()->subDay()->startOfDay(), $today->copy()->subDay()->endOfDay(), 'Yesterday'],
            'this_week' => [$today->copy()->startOfWeek()->startOfDay(), $today->copy()->endOfWeek()->endOfDay(), 'This Week'],
            'last_week' => [$today->copy()->subWeek()->startOfWeek()->startOfDay(), $today->copy()->subWeek()->endOfWeek()->endOfDay(), 'Last Week'],
            'this_month' => [$today->copy()->startOfMonth()->startOfDay(), $today->copy()->endOfMonth()->endOfDay(), 'This Month'],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth()->startOfDay(), $today->copy()->subMonthNoOverflow()->endOfMonth()->endOfDay(), 'Last Month'],
            'custom' => $this->customRange($customStartDate, $customEndDate),
            default => throw new InvalidArgumentException("Unsupported report date range [{$range}]."),
        };

        return [
            'start_date' => $start,
            'end_date' => $end,
            'label' => $label,
            'slug' => $this->slug($range, $start, $end),
        ];
    }

    public function options(): array
    {
        return [
            'today' => 'Today',
            'yesterday' => 'Yesterday',
            'this_week' => 'This Week',
            'last_week' => 'Last Week',
            'this_month' => 'This Month',
            'last_month' => 'Last Month',
            'custom' => 'Custom',
        ];
    }

    private function customRange(?string $customStartDate, ?string $customEndDate): array
    {
        $start = filled($customStartDate)
            ? Carbon::parse($customStartDate)->startOfDay()
            : now()->startOfDay();

        $end = filled($customEndDate)
            ? Carbon::parse($customEndDate)->endOfDay()
            : $start->copy()->endOfDay();

        if ($end->lt($start)) {
            $end = $start->copy()->endOfDay();
        }

        return [$start, $end, $start->format('d-M-Y').' to '.$end->format('d-M-Y')];
    }

    private function slug(string $range, Carbon $start, Carbon $end): string
    {
        if ($range !== 'custom') {
            return str_replace('_', '-', $range);
        }

        return $start->format('Y-m-d').'-to-'.$end->format('Y-m-d');
    }
}
