@php
    $searchPlaceholder = $searchPlaceholder ?? 'Search';
@endphp

<div class="mb-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
    <div class="grid gap-4 md:grid-cols-6">
        <label class="space-y-1">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Date Range</span>
            <select wire:model.live="dateRange" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950">
                @foreach ($this->dateRangeOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>

        @if ($this->dateRange === 'custom')
            <label class="space-y-1">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Start Date</span>
                <input type="date" wire:model.live="customStartDate" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950">
            </label>
            <label class="space-y-1">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">End Date</span>
                <input type="date" wire:model.live="customEndDate" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950">
            </label>
        @endif

        <label class="space-y-1">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Search</span>
            <input type="search" wire:model.live.debounce.500ms="tableSearch" placeholder="{{ $searchPlaceholder }}" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950">
        </label>

        <label class="space-y-1">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Balance Type</span>
            <select wire:model.live="balanceType" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950">
                <option value="all">All</option>
                <option value="debit">Debit Balance</option>
                <option value="credit">Credit Balance</option>
                <option value="zero">Zero Balance</option>
            </select>
        </label>

        <label class="space-y-1">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Status</span>
            <select wire:model.live="status" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-950">
                <option value="all">All</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </label>

        <div class="flex items-end gap-2">
            <button type="button" wire:click="applyFilters" class="rounded-md bg-blue-700 px-3 py-2 text-sm font-medium text-white">Apply</button>
            <button type="button" wire:click="resetFilters" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium dark:border-gray-700">Reset</button>
        </div>
    </div>

    <div class="mt-3 flex flex-wrap items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
        <span class="font-medium">Showing:</span>
        <span>{{ $this->reportDateLabel() }}</span>
        <span>({{ \Illuminate\Support\Carbon::parse($this->reportStartDate())->format('d-M-Y') }} to {{ \Illuminate\Support\Carbon::parse($this->reportEndDate())->format('d-M-Y') }})</span>
    </div>

    <div class="mt-4 flex flex-wrap gap-2">
        <a href="{{ $this->exportUrl($exportRoute, 'csv') }}" class="rounded-md bg-blue-700 px-3 py-2 text-sm font-medium text-white">Export Excel</a>
        <a href="{{ $this->printUrl($printRoute, true) }}" target="_blank" class="rounded-md bg-gray-900 px-3 py-2 text-sm font-medium text-white dark:bg-gray-100 dark:text-gray-900">Export PDF</a>
        <a href="{{ $this->printUrl($printRoute) }}" target="_blank" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium dark:border-gray-700">Print</a>
    </div>
</div>
