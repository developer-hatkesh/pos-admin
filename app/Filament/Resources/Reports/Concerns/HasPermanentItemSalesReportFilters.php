<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\Concerns;

use App\Models\Brand;
use App\Models\Category;
use App\Services\Reports\ReportDateRangeService;
use Illuminate\Database\Eloquent\Builder;

trait HasPermanentItemSalesReportFilters
{
    public string $dateRange = 'today';

    public ?string $customStartDate = null;

    public ?string $customEndDate = null;

    public string $categoryId = 'all';

    public string $brandId = 'all';

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

    public function categoryOptions(): array
    {
        return Category::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function brandOptions(): array
    {
        return Brand::query()->orderBy('name')->pluck('name', 'id')->all();
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

    public function updatedCategoryId(): void
    {
        $this->resetTablePage();
    }

    public function updatedBrandId(): void
    {
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
        $this->categoryId = 'all';
        $this->brandId = 'all';
        $this->tableSearch = '';

        $this->resetTablePage();
    }

    public function applyItemSalesFilters(Builder $query): Builder
    {
        return $query
            ->when($this->categoryId !== 'all' && filled($this->categoryId), fn (Builder $query): Builder => $query->where('category_id', $this->categoryId))
            ->when($this->brandId !== 'all' && filled($this->brandId), fn (Builder $query): Builder => $query->where('brand_id', $this->brandId));
    }

    private function resetTablePage(): void
    {
        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }
}
