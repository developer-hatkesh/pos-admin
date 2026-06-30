@php
    $searchPlaceholder = $searchPlaceholder ?? 'Search';
    $fromDate = \Illuminate\Support\Carbon::parse($this->reportStartDate())->format('d-M-Y');
    $toDate = \Illuminate\Support\Carbon::parse($this->reportEndDate())->format('d-M-Y');
@endphp

<div class="mb-5 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm ring-1 ring-gray-950/5 dark:border-gray-800 dark:bg-gray-900 dark:ring-white/10">
    <div class="flex flex-col gap-3 border-b border-gray-100 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-gray-950/40 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0">
            <div class="text-sm font-semibold text-gray-950 dark:text-white">Report filters</div>
            <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-600 dark:text-gray-300">
                <span class="font-medium text-gray-800 dark:text-gray-100">{{ $this->reportDateLabel() }}</span>
                <span class="text-gray-400 dark:text-gray-500">/</span>
                <span>{{ $fromDate }} to {{ $toDate }}</span>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ $this->exportUrl($exportRoute, 'csv') }}" class="inline-flex min-h-9 items-center justify-center rounded-md bg-blue-700 px-3.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2 dark:focus:ring-offset-gray-900">Export Excel</a>
            <a href="{{ $this->printUrl($printRoute, true) }}" target="_blank" class="inline-flex min-h-9 items-center justify-center rounded-md bg-gray-950 px-3.5 text-sm font-semibold text-white shadow-sm transition hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-700 focus:ring-offset-2 dark:bg-white dark:text-gray-950 dark:hover:bg-gray-200 dark:focus:ring-offset-gray-900">Export PDF</a>
            <a href="{{ $this->printUrl($printRoute) }}" target="_blank" class="inline-flex min-h-9 items-center justify-center rounded-md border border-gray-300 bg-white px-3.5 text-sm font-semibold text-gray-800 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:hover:bg-gray-800 dark:focus:ring-offset-gray-900">Print</a>
        </div>
    </div>

    <div class="grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-12">
        <label class="space-y-1.5 xl:col-span-2">
            <span class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Date Range</span>
            <select wire:model.live="dateRange" class="block min-h-10 w-full rounded-md border-gray-300 bg-white text-sm text-gray-950 shadow-sm focus:border-blue-600 focus:ring-blue-600 dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                @foreach ($this->dateRangeOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>

        @if ($this->dateRange === 'custom')
            <label class="space-y-1.5 xl:col-span-2">
                <span class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Start Date</span>
                <input type="date" wire:model.live="customStartDate" class="block min-h-10 w-full rounded-md border-gray-300 bg-white text-sm text-gray-950 shadow-sm focus:border-blue-600 focus:ring-blue-600 dark:border-gray-700 dark:bg-gray-950 dark:text-white">
            </label>
            <label class="space-y-1.5 xl:col-span-2">
                <span class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">End Date</span>
                <input type="date" wire:model.live="customEndDate" class="block min-h-10 w-full rounded-md border-gray-300 bg-white text-sm text-gray-950 shadow-sm focus:border-blue-600 focus:ring-blue-600 dark:border-gray-700 dark:bg-gray-950 dark:text-white">
            </label>
        @endif

        <label class="space-y-1.5 xl:col-span-3">
            <span class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Search</span>
            <input type="search" wire:model.live.debounce.500ms="tableSearch" placeholder="{{ $searchPlaceholder }}" class="block min-h-10 w-full rounded-md border-gray-300 bg-white text-sm text-gray-950 shadow-sm placeholder:text-gray-400 focus:border-blue-600 focus:ring-blue-600 dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:placeholder:text-gray-500">
        </label>

        <label class="space-y-1.5 xl:col-span-2">
            <span class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Balance Type</span>
            <select wire:model.live="balanceType" class="block min-h-10 w-full rounded-md border-gray-300 bg-white text-sm text-gray-950 shadow-sm focus:border-blue-600 focus:ring-blue-600 dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                <option value="all">All</option>
                <option value="debit">Debit Balance</option>
                <option value="credit">Credit Balance</option>
                <option value="zero">Zero Balance</option>
            </select>
        </label>

        <label class="space-y-1.5 xl:col-span-2">
            <span class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Status</span>
            <select wire:model.live="status" class="block min-h-10 w-full rounded-md border-gray-300 bg-white text-sm text-gray-950 shadow-sm focus:border-blue-600 focus:ring-blue-600 dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                <option value="all">All</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </label>

        <div class="flex items-end gap-2 xl:col-span-3">
            <button type="button" wire:click="applyFilters" class="inline-flex min-h-10 flex-1 items-center justify-center rounded-md bg-blue-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2 dark:focus:ring-offset-gray-900 sm:flex-none">Apply</button>
            <button type="button" wire:click="resetFilters" class="inline-flex min-h-10 flex-1 items-center justify-center rounded-md border border-gray-300 bg-white px-4 text-sm font-semibold text-gray-800 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:hover:bg-gray-800 dark:focus:ring-offset-gray-900 sm:flex-none">Reset</button>
        </div>
    </div>
</div>
