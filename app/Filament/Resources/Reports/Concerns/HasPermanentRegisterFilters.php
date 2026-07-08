<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\Concerns;

use App\Models\Customer;
use App\Models\Supplier;
use App\Services\Reports\ReportDateRangeService;
use Illuminate\Database\Eloquent\Builder;

trait HasPermanentRegisterFilters
{
    public string $dateRange = 'today';

    public ?string $customStartDate = null;

    public ?string $customEndDate = null;

    public string $partyId = 'all';

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

    public function partyLabel(): string
    {
        return $this->registerPartyType() === 'supplier' ? 'Supplier' : 'Customer';
    }

    public function partyOptions(): array
    {
        $model = $this->registerPartyType() === 'supplier' ? Supplier::class : Customer::class;

        return $model::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
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

    public function updatedPartyId(): void
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
        $this->partyId = 'all';
        $this->tableSearch = '';

        $this->resetTablePage();
    }

    public function applyRegisterFilters(Builder $query): Builder
    {
        return $query
            ->whereDate($this->registerDateColumn(), '>=', $this->reportStartDate())
            ->whereDate($this->registerDateColumn(), '<=', $this->reportEndDate())
            ->when($this->partyId !== 'all' && filled($this->partyId), fn (Builder $query): Builder => $query->where($this->registerPartyColumn(), $this->partyId));
    }

    private function registerPartyType(): string
    {
        return property_exists($this, 'registerPartyType') && $this->registerPartyType === 'supplier'
            ? 'supplier'
            : 'customer';
    }

    private function registerDateColumn(): string
    {
        return property_exists($this, 'registerDateColumn')
            ? $this->registerDateColumn
            : 'invoice_date';
    }

    private function registerPartyColumn(): string
    {
        return property_exists($this, 'registerPartyColumn')
            ? $this->registerPartyColumn
            : 'customer_id';
    }

    private function resetTablePage(): void
    {
        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }
}
