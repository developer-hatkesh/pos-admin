@php
    $report = $this->getReport();
    $money = fn (float|int|string|null $amount): string => app_money($amount);
@endphp

<x-filament-panels::page>
    <div class="space-y-5" wire:loading.class="opacity-60">
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                    <label class="space-y-1.5">
                        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Date Range</span>
                        <select wire:model.live="dateRange" class="w-full rounded-md border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                            @foreach ($this->dateRangeOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="space-y-1.5">
                        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">VAT Quarter</span>
                        <select wire:model.live="vatQuarter" class="w-full rounded-md border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                            @foreach ($this->vatQuarterOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="space-y-1.5">
                        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Customer</span>
                        <select wire:model.live="customerId" class="w-full rounded-md border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                            <option value="">All customers</option>
                            @foreach ($this->customerOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="space-y-1.5">
                        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Supplier</span>
                        <select wire:model.live="supplierId" class="w-full rounded-md border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                            <option value="">All suppliers</option>
                            @foreach ($this->supplierOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="space-y-1.5">
                        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">VAT Type</span>
                        <select wire:model.live="vatType" class="w-full rounded-md border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                            <option value="">All VAT rates</option>
                            @foreach ($this->vatTypeOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="space-y-1.5">
                        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Branch / Location</span>
                        <select disabled class="w-full rounded-md border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-500 shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-gray-400">
                            <option>Not available</option>
                        </select>
                    </label>
                </div>

                <div class="flex flex-wrap gap-2 xl:justify-end">
                    <button type="button" wire:click="applyFilters" class="ledger-report-button ledger-report-button--primary">Apply</button>
                    <button type="button" wire:click="resetFilters" class="ledger-report-button ledger-report-button--secondary">Reset</button>
                    <a href="{{ $this->exportUrl('csv') }}" class="ledger-report-button ledger-report-button--primary">CSV</a>
                    <a href="{{ $this->exportUrl('xlsx') }}" class="ledger-report-button ledger-report-button--primary">Excel</a>
                    <a href="{{ $this->exportUrl('pdf') }}" target="_blank" class="ledger-report-button ledger-report-button--secondary">PDF</a>
                    <a href="{{ $this->printUrl() }}" target="_blank" class="ledger-report-button ledger-report-button--secondary">Print</a>
                </div>
            </div>

            <div class="mt-3 text-xs font-medium text-gray-600 dark:text-gray-300">
                {{ $this->reportDateLabel() }}. Branch / warehouse / location filtering is not available because no existing location columns were found.
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-4">
            @foreach ([1, 4, 5, 6] as $box)
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="text-xs font-bold uppercase tracking-normal text-gray-500">Box {{ $box }}</div>
                    <div class="mt-2 text-2xl font-bold text-gray-950 dark:text-white">{{ $money($report['boxes'][$box]['amount']) }}</div>
                    <div class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $report['boxes'][$box]['label'] }}</div>
                </div>
            @endforeach
        </div>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
            <div class="space-y-5">
                @foreach ($report['sections'] as $section)
                    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                            <div>
                                <h2 class="text-base font-bold text-gray-950 dark:text-white">{{ $section['title'] }}</h2>
                                @isset($section['note'])
                                    <p class="mt-1 text-xs text-gray-500">{{ $section['note'] }}</p>
                                @endisset
                            </div>
                            <div class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                VAT {{ $money($section['summary']['vat']) }}
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[760px] divide-y divide-gray-200 text-sm dark:divide-gray-800">
                                <thead class="bg-gray-50 dark:bg-gray-950">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Date</th>
                                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Type</th>
                                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Number</th>
                                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Party / Category</th>
                                        <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-200">Net</th>
                                        <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-200">VAT</th>
                                        <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-200">Gross</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @forelse ($section['rows'] as $row)
                                        <tr>
                                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ optional($row['date'])->format('d-M-Y') }}</td>
                                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $row['type'] }}</td>
                                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $row['number'] }}</td>
                                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $row['party'] }}</td>
                                            <td class="px-4 py-3 text-right font-medium text-gray-950 dark:text-white">{{ $money($row['net']) }}</td>
                                            <td class="px-4 py-3 text-right font-medium text-gray-950 dark:text-white">{{ $money($row['vat']) }}</td>
                                            <td class="px-4 py-3 text-right font-medium text-gray-950 dark:text-white">{{ $money($row['gross']) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">No records found for this section.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                <tfoot class="bg-gray-50 dark:bg-gray-950">
                                    <tr>
                                        <td colspan="4" class="px-4 py-3 text-right font-bold text-gray-700 dark:text-gray-200">Total ({{ $section['summary']['count'] }} rows)</td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-950 dark:text-white">{{ $money($section['summary']['net']) }}</td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-950 dark:text-white">{{ $money($section['summary']['vat']) }}</td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-950 dark:text-white">{{ $money($section['summary']['gross']) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="space-y-5">
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="text-base font-bold text-gray-950 dark:text-white">HMRC VAT Boxes</h2>
                    <div class="mt-4 divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($report['boxes'] as $box => $data)
                            <div class="grid grid-cols-[64px_1fr_auto] items-center gap-3 py-3">
                                <div class="font-bold text-gray-950 dark:text-white">Box {{ $box }}</div>
                                <div class="text-sm text-gray-600 dark:text-gray-300">{{ $data['label'] }}</div>
                                <div class="font-bold text-gray-950 dark:text-white">{{ $money($data['amount']) }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="text-base font-bold text-gray-950 dark:text-white">Overall Summary</h2>
                    <div class="mt-4 space-y-3">
                        @foreach ($report['overall'] as $label => $amount)
                            <div class="flex items-center justify-between gap-4">
                                <span class="text-sm font-medium text-gray-600 dark:text-gray-300">{{ str($label)->replace('_', ' ')->title() }}</span>
                                <span class="font-bold text-gray-950 dark:text-white">{{ $money($amount) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="text-base font-bold text-gray-950 dark:text-white">VAT Breakdown</h2>
                    <div class="mt-4 space-y-3">
                        @php $maxVat = max(1, ...array_map(fn ($row) => abs((float) $row['vat']), $report['charts'])); @endphp
                        @foreach ($report['charts'] as $row)
                            <div>
                                <div class="mb-1 flex justify-between text-xs font-semibold text-gray-600 dark:text-gray-300">
                                    <span>{{ $row['label'] }}</span>
                                    <span>{{ $money($row['vat']) }}</span>
                                </div>
                                <div class="h-2 rounded bg-gray-100 dark:bg-gray-800">
                                    <div class="h-2 rounded bg-primary-600" style="width: {{ min(100, (abs((float) $row['vat']) / $maxVat) * 100) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="text-base font-bold text-gray-950 dark:text-white">Payment Summary</h2>
                    <div class="mt-4 space-y-3">
                        <div class="flex justify-between"><span class="text-sm text-gray-600 dark:text-gray-300">Receipts</span><span class="font-bold">{{ $money($report['payments']['receipts']) }}</span></div>
                        <div class="flex justify-between"><span class="text-sm text-gray-600 dark:text-gray-300">Payments</span><span class="font-bold">{{ $money($report['payments']['payments']) }}</span></div>
                        <div class="flex justify-between"><span class="text-sm text-gray-600 dark:text-gray-300">Net Cash Flow</span><span class="font-bold">{{ $money($report['payments']['net_cash_flow']) }}</span></div>
                        <div class="text-xs text-gray-500">{{ $report['payments']['count'] }} posted vouchers in period.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
