@php
    $searchPlaceholder = $searchPlaceholder ?? 'Search';
    $fromDate = \Illuminate\Support\Carbon::parse($this->reportStartDate())->format('d-M-Y');
    $toDate = \Illuminate\Support\Carbon::parse($this->reportEndDate())->format('d-M-Y');
    $labelClass = 'block text-xs font-semibold uppercase text-gray-600 dark:text-gray-300';
    $controlClass = 'block h-11 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-950 shadow-sm outline-none transition placeholder:text-gray-400 focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:placeholder:text-gray-500';
    $buttonClass = 'inline-flex h-10 items-center justify-center rounded-md px-4 text-sm font-semibold text-white shadow-sm transition focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-900';
@endphp

<div class="mb-5 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm ring-1 ring-gray-950/5 dark:border-gray-800 dark:bg-gray-900 dark:ring-white/10">
    <div class="flex flex-col gap-4 border-b border-gray-100 bg-gray-50 px-5 py-4 dark:border-gray-800 dark:bg-gray-950/40 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0">
            <div class="text-sm font-semibold text-gray-950 dark:text-white">Report filters</div>
            <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-600 dark:text-gray-300">
                <span class="font-medium text-gray-800 dark:text-gray-100">{{ $this->reportDateLabel() }}</span>
                <span class="text-gray-400 dark:text-gray-500">/</span>
                <span>{{ $fromDate }} to {{ $toDate }}</span>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ $this->exportUrl($exportRoute, 'csv') }}" class="{{ $buttonClass }} bg-blue-700 hover:bg-blue-800 focus:ring-blue-600">Export Excel</a>
            <a href="{{ $this->printUrl($printRoute, true) }}" target="_blank" class="{{ $buttonClass }} bg-gray-950 hover:bg-gray-800 focus:ring-gray-700 dark:bg-gray-100 dark:text-gray-950 dark:hover:bg-white">Export PDF</a>
            <a href="{{ $this->printUrl($printRoute) }}" target="_blank" class="{{ $buttonClass }} bg-emerald-700 hover:bg-emerald-800 focus:ring-emerald-600">Print</a>
        </div>
    </div>

    <div class="grid gap-4 p-5 sm:p-6 md:grid-cols-2 xl:grid-cols-12">
        <label class="space-y-2 xl:col-span-2">
            <span class="{{ $labelClass }}">Date Range</span>
            <select wire:model.live="dateRange" class="{{ $controlClass }}">
                @foreach ($this->dateRangeOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>

        @if ($this->dateRange === 'custom')
            <label class="space-y-2 xl:col-span-2">
                <span class="{{ $labelClass }}">Start Date</span>
                <input type="date" wire:model.live="customStartDate" class="{{ $controlClass }}">
            </label>
            <label class="space-y-2 xl:col-span-2">
                <span class="{{ $labelClass }}">End Date</span>
                <input type="date" wire:model.live="customEndDate" class="{{ $controlClass }}">
            </label>
        @endif

        <label class="space-y-2 xl:col-span-3">
            <span class="{{ $labelClass }}">Search</span>
            <input type="search" wire:model.live.debounce.500ms="tableSearch" placeholder="{{ $searchPlaceholder }}" class="{{ $controlClass }}">
        </label>

        <label class="space-y-2 xl:col-span-2">
            <span class="{{ $labelClass }}">Balance Type</span>
            <select wire:model.live="balanceType" class="{{ $controlClass }}">
                <option value="all">All</option>
                <option value="debit">Debit Balance</option>
                <option value="credit">Credit Balance</option>
                <option value="zero">Zero Balance</option>
            </select>
        </label>

        <label class="space-y-2 xl:col-span-2">
            <span class="{{ $labelClass }}">Status</span>
            <select wire:model.live="status" class="{{ $controlClass }}">
                <option value="all">All</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </label>

        <div class="flex items-end gap-2 xl:col-span-3">
            <button type="button" wire:click="applyFilters" class="{{ $buttonClass }} flex-1 bg-blue-700 hover:bg-blue-800 focus:ring-blue-600 sm:flex-none">Apply</button>
            <button type="button" wire:click="resetFilters" class="{{ $buttonClass }} flex-1 bg-gray-700 hover:bg-gray-800 focus:ring-gray-600 sm:flex-none">Reset</button>
        </div>
    </div>
</div>
