@php
    $report = $this->getReport();
    $summary = $report['summary'];
    $ratios = $report['ratios'];
    $money = fn (float|int|string|null $amount): string => \App\Services\Reports\CurrencyService::format($amount);
    $ratio = fn (?float $value): string => $value === null ? '-' : number_format($value, 2);
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
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
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Branch</span>
                    <select disabled class="w-full rounded-md border-gray-300 bg-gray-50 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-950">
                        <option>All Branches</option>
                    </select>
                </label>

                <label class="flex items-end gap-2 pb-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                    <input type="checkbox" wire:model.live="showZeroBalances" class="rounded border-gray-300">
                    Show zero balance accounts
                </label>

                <div class="flex items-end gap-2 md:col-span-2">
                    <button type="button" wire:click="applyFilters" class="rounded-md bg-blue-700 px-3 py-2 text-sm font-medium text-white">Apply</button>
                    <button type="button" wire:click="resetFilters" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium dark:border-gray-700">Reset</button>
                    <button type="button" wire:click="expandAll" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium dark:border-gray-700">Expand All</button>
                    <button type="button" wire:click="collapseAll" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium dark:border-gray-700">Collapse All</button>
                    <a href="{{ $this->exportUrl('pdf') }}" target="_blank" class="rounded-md bg-gray-900 px-3 py-2 text-sm font-medium text-white dark:bg-gray-100 dark:text-gray-900">Export PDF</a>
                    <a href="{{ $this->exportUrl('csv') }}" class="rounded-md bg-blue-700 px-3 py-2 text-sm font-medium text-white">Export Excel</a>
                    <a href="{{ $this->printUrl() }}" target="_blank" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium dark:border-gray-700">Print</a>
                </div>
            </div>
            <div class="mt-3 text-sm font-medium text-gray-600 dark:text-gray-300">{{ $this->reportDateLabel() }}</div>
        </div>

        <div class="rounded-lg border border-blue-200 bg-white p-5 shadow-sm dark:border-blue-900 dark:bg-gray-900">
            <div class="grid gap-4 md:grid-cols-3">
                <div class="flex gap-3">
                    <div class="grid h-14 w-14 place-items-center rounded-md bg-blue-800 text-xl font-bold text-white">
                        {{ strtoupper(substr((string) ($report['company']?->name ?? config('app.name')), 0, 1)) }}
                    </div>
                    <div>
                        <div class="text-xl font-bold text-gray-950 dark:text-white">{{ $report['company']?->name ?? config('app.name') }}</div>
                        <div class="text-sm leading-6 text-gray-600 dark:text-gray-300">
                            {{ $report['company']?->address }}{{ $report['company']?->city ? ', '.$report['company']->city : '' }}{{ $report['company']?->postcode ? ' - '.$report['company']->postcode : '' }}<br>
                            Phone: {{ $report['company']?->phone ?? '-' }} | Email: {{ $report['company']?->email ?? '-' }}
                        </div>
                    </div>
                </div>

                <div class="text-center">
                    <h2 class="text-3xl font-bold uppercase tracking-normal text-gray-950 dark:text-white">Balance Sheet</h2>
                    <div class="mt-2 text-base font-semibold text-gray-700 dark:text-gray-200">{{ $this->reportDateLabel() }}</div>
                </div>

                <div class="grid justify-end gap-2 text-sm text-gray-700 dark:text-gray-200">
                    <div class="grid grid-cols-[90px_12px_1fr]"><span>Date</span><span>:</span><span>{{ $report['generated_at']->format('d-M-Y') }}</span></div>
                    <div class="grid grid-cols-[90px_12px_1fr]"><span>Time</span><span>:</span><span>{{ $report['generated_at']->format('h:i A') }}</span></div>
                    <div class="grid grid-cols-[90px_12px_1fr]"><span>Page</span><span>:</span><span>1 of 1</span></div>
                </div>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            @foreach (['assets', 'liabilities_equity'] as $side)
                @php($section = $report['sections'][$side])
                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div @class([
                        'px-5 py-3 text-lg font-bold uppercase tracking-normal text-white',
                        'bg-green-700' => $side === 'assets',
                        'bg-red-700' => $side === 'liabilities_equity',
                    ])>
                        {{ $section['title'] }}
                    </div>

                    <div class="divide-y divide-gray-200 dark:divide-gray-800">
                        @foreach ($section['groups'] as $group)
                            <div>
                                <button type="button" wire:click="toggleGroup('{{ $group['key'] }}')" class="flex w-full items-center justify-between bg-gray-50 px-5 py-3 text-left font-semibold text-gray-950 dark:bg-gray-950 dark:text-white">
                                    <span>{{ $this->isExpanded($group['key']) ? 'v' : '>' }} {{ $group['name'] }}</span>
                                    <span>{{ $money($group['amount']) }}</span>
                                </button>

                                @if ($this->isExpanded($group['key']))
                                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                                        @forelse ($group['categories'] as $category)
                                            <div>
                                                <button type="button" wire:click="toggleGroup('{{ $category['key'] }}')" class="flex w-full items-center justify-between px-5 py-3 text-left text-sm font-semibold text-gray-800 dark:text-gray-100">
                                                    <span>{{ $this->isExpanded($category['key']) ? 'v' : '>' }} {{ $category['name'] }}</span>
                                                    <a href="{{ $category['drill_url'] }}" class="text-blue-700 hover:underline dark:text-blue-300">{{ $money($category['amount']) }}</a>
                                                </button>

                                                @if ($this->isExpanded($category['key']))
                                                    <div class="bg-gray-50 px-5 py-2 dark:bg-gray-950">
                                                        @foreach ($category['ledgers'] as $ledger)
                                                            <div class="grid grid-cols-[90px_1fr_auto] gap-3 py-2 text-sm text-gray-700 dark:text-gray-200">
                                                                <span>{{ $ledger['code'] }}</span>
                                                                <a href="{{ $ledger['drill_url'] }}" class="hover:underline">{{ $ledger['name'] }}</a>
                                                                <span class="font-medium">{{ $money($ledger['statement_amount']) }}</span>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        @empty
                                            <div class="px-5 py-3 text-sm text-gray-500">No accounts in this group.</div>
                                        @endforelse
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div @class([
                        'flex items-center justify-between px-5 py-4 text-base font-bold text-white',
                        'bg-green-800' => $side === 'assets',
                        'bg-blue-800' => $side === 'liabilities_equity',
                    ])>
                        <span>{{ $side === 'assets' ? 'Total Assets' : 'Total Liabilities + Equity' }}</span>
                        <span>{{ $money($side === 'assets' ? $summary['total_assets'] : $summary['total_liabilities_equity']) }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        <div @class([
            'rounded-lg border p-4 text-sm font-semibold',
            'border-green-300 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200' => $summary['is_balanced'],
            'border-red-300 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200' => ! $summary['is_balanced'],
        ])>
            @if ($summary['is_balanced'])
                Balance Sheet Balanced
            @else
                Balance Sheet not balanced. Difference: {{ $money($summary['difference']) }}
            @endif
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            @foreach ([
                'Total Assets' => $summary['total_assets'],
                'Total Liabilities' => $summary['total_liabilities'],
                'Total Equity' => $summary['total_equity'],
                'Working Capital' => $summary['working_capital'],
                'Cash Balance' => $summary['cash_balance'],
                'Bank Balance' => $summary['bank_balance'],
                'Accounts Receivable' => $summary['accounts_receivable'],
                'Accounts Payable' => $summary['accounts_payable'],
                'Net Assets' => $ratios['net_assets'],
            ] as $label => $amount)
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs font-semibold uppercase tracking-normal text-gray-500">{{ $label }}</div>
                    <div class="mt-2 text-xl font-bold text-gray-950 dark:text-white">{{ $money($amount) }}</div>
                </div>
            @endforeach

            @foreach ([
                'Current Ratio' => $ratios['current_ratio'],
                'Quick Ratio' => $ratios['quick_ratio'],
                'Debt Ratio' => $ratios['debt_ratio'],
                'Debt to Equity' => $ratios['debt_to_equity'],
            ] as $label => $value)
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs font-semibold uppercase tracking-normal text-gray-500">{{ $label }}</div>
                    <div class="mt-2 text-xl font-bold text-gray-950 dark:text-white">{{ $ratio($value) }}</div>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
