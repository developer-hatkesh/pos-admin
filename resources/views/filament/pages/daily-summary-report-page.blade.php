@php
    $report = $this->getReport();
    $money = fn (float|int|string|null $amount): string => \App\Services\Reports\CurrencyService::format($amount);
    $metricRows = [
        ['label' => 'Total Sale', 'value' => $money($report['sales']['total'])],
        ['label' => 'Cash', 'value' => $money($report['sales']['cash'])],
        ['label' => 'Credit', 'value' => $money($report['sales']['credit'])],
        ['label' => 'Bank Transfer', 'value' => $money($report['sales']['bank_transfer'])],
        ['label' => 'Card Payment', 'value' => $money($report['sales']['card_payment'])],
        ['label' => 'Expenses', 'value' => $money($report['outgoings']['expenses'])],
        ['label' => 'Wages', 'value' => $money($report['outgoings']['wages'])],
    ];
@endphp

<x-filament-panels::page>
    <div class="space-y-5">
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end">
                    <label class="w-full space-y-1.5 lg:w-64">
                        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Date Range</span>
                        <select wire:model.live="dateRange" class="w-full rounded-md border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                            @foreach ($this->dateRangeOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <span class="block text-xs font-medium text-gray-600 dark:text-gray-300">{{ $this->reportDateLabel() }}</span>
                    </label>

                    @if ($this->dateRange === 'custom')
                        <label class="w-full space-y-1.5 lg:w-56">
                            <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Start Date</span>
                            <input type="date" wire:model.live="customStartDate" class="w-full rounded-md border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                        </label>

                        <label class="w-full space-y-1.5 lg:w-56">
                            <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">End Date</span>
                            <input type="date" wire:model.live="customEndDate" class="w-full rounded-md border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                        </label>
                    @endif

                    <div class="flex gap-2 pb-5 lg:pb-0">
                        <button type="button" wire:click="applyFilters" class="ledger-report-button ledger-report-button--primary">Apply</button>
                        <button type="button" wire:click="resetFilters" class="ledger-report-button ledger-report-button--secondary">Reset</button>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 xl:justify-end xl:pb-5">
                    <a href="{{ $this->exportUrl('csv') }}" class="ledger-report-button ledger-report-button--primary">Export Excel</a>
                    <a href="{{ $this->exportUrl('pdf') }}" target="_blank" class="ledger-report-button ledger-report-button--secondary">Export PDF</a>
                    <a href="{{ $this->printUrl() }}" target="_blank" class="ledger-report-button ledger-report-button--secondary">Print</a>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-950 dark:text-white">Summary Report</h2>
                        <div class="mt-1 text-sm font-medium text-gray-600 dark:text-gray-300">
                            {{ $report['company']?->name ?? config('app.name') }} | {{ $report['start_date']->format('d-M-Y') }} to {{ $report['end_date']->format('d-M-Y') }}
                        </div>
                    </div>
                    <div class="text-right text-sm text-gray-600 dark:text-gray-300">
                        <div>Generated {{ $report['generated_at']->format('d-M-Y h:i A') }}</div>
                    </div>
                </div>
            </div>

            <div class="grid gap-0 lg:grid-cols-[minmax(0,1fr)_360px]">
                <div class="divide-y divide-gray-200 dark:divide-gray-800">
                    @foreach ($metricRows as $row)
                        <div class="grid grid-cols-[1fr_auto] items-center gap-4 px-6 py-4">
                            <div class="text-sm font-bold uppercase tracking-normal text-gray-700 dark:text-gray-200">{{ $row['label'] }}</div>
                            <div class="text-lg font-bold text-gray-950 dark:text-white">{{ $row['value'] }}</div>
                        </div>
                    @endforeach

                    <div class="grid gap-2 px-6 py-5">
                        <div class="text-sm font-bold uppercase tracking-normal text-gray-700 dark:text-gray-200">Total Quantity of Perfume Today Sold Out</div>
                        <div class="text-3xl font-bold text-gray-950 dark:text-white">{{ number_format((float) $report['stock']['perfume_qty_sold'], 3) }}</div>
                    </div>

                    <div class="grid grid-cols-[1fr_auto] items-center gap-4 bg-gray-50 px-6 py-4 dark:bg-gray-950">
                        <div class="text-sm font-bold uppercase tracking-normal text-gray-700 dark:text-gray-200">Total Cash</div>
                        <div class="text-xl font-bold text-gray-950 dark:text-white">{{ $money($report['sales']['cash']) }}</div>
                    </div>

                    <div class="grid grid-cols-[1fr_auto] items-center gap-4 bg-gray-50 px-6 py-4 dark:bg-gray-950">
                        <div class="text-sm font-bold uppercase tracking-normal text-gray-700 dark:text-gray-200">Total Credit</div>
                        <div class="text-xl font-bold text-gray-950 dark:text-white">{{ $money($report['sales']['credit']) }}</div>
                    </div>
                </div>

                <div class="border-t border-gray-200 bg-gray-50 p-5 dark:border-gray-800 dark:bg-gray-950 lg:border-l lg:border-t-0">
                    <div class="text-sm font-bold uppercase tracking-normal text-gray-700 dark:text-gray-200">Bank Balances</div>
                    <div class="mt-4 space-y-3">
                        @forelse ($report['bank_balances'] as $index => $bank)
                            <div class="rounded-md border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                                <div class="text-xs font-bold uppercase tracking-normal text-gray-500">Total Amount in {{ $bank['name'] !== '' ? $bank['name'] : 'Bank '.($index + 1) }}</div>
                                <div class="mt-2 text-xl font-bold text-gray-950 dark:text-white">{{ $money($bank['amount']) }}</div>
                            </div>
                        @empty
                            <div class="rounded-md border border-dashed border-gray-300 p-4 text-sm text-gray-500 dark:border-gray-700">No bank accounts found.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
