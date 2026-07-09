<x-filament-panels::page>
    <div class="flux-dashboard">
        <div class="flux-dashboard__header">
            <div>
                <h1 class="flux-dashboard__title">Today's Summary</h1>
            </div>
            <div class="flux-dashboard__date">
                {{ now()->format('D, d M Y') }}
            </div>
        </div>

        <div class="flux-metric-grid">
            @foreach ($metrics as $metric)
                <div class="flux-metric-card flux-metric-card--{{ $metric['tone'] }}">
                    <div class="flux-metric-card__icon">
                        <x-filament::icon :icon="$metric['icon']" />
                    </div>
                    <div class="flux-metric-card__body">
                        <p>{{ $metric['label'] }}</p>
                        <strong>{{ $metric['value'] }}</strong>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flux-dashboard-grid">
            <section class="flux-widget">
                <div class="flux-widget__header">
                    <div>
                        <h2>Recent Sales</h2>
                        <p>Latest invoices</p>
                    </div>
                </div>

                <div class="flux-table-wrap">
                    <table class="flux-table">
                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th class="flux-table__number">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentSales as $sale)
                                <tr>
                                    <td>{{ $sale['invoiceNo'] }}</td>
                                    <td>{{ $sale['date'] }}</td>
                                    <td>{{ $sale['customer'] }}</td>
                                    <td class="flux-table__number">{{ app_money($sale['total']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="flux-empty">No recent sales.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-filament-panels::page>
