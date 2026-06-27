<x-filament-panels::page>
    <div class="flux-dashboard">
        <div class="flux-dashboard__header">
            <div>
                <p class="flux-dashboard__eyebrow">Perfume POS</p>
                <h1 class="flux-dashboard__title">Business overview</h1>
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

        <div class="flux-dashboard-grid flux-dashboard-grid--two">
            <section class="flux-widget">
                <div class="flux-widget__header">
                    <div>
                        <h2>This Week Sales & Purchases</h2>
                        <p>Daily invoice totals</p>
                    </div>
                    <div class="flux-widget__legend">
                        <span><i class="flux-dot flux-dot--sales"></i>Sales</span>
                        <span><i class="flux-dot flux-dot--purchase"></i>Purchases</span>
                    </div>
                </div>

                <div class="flux-bar-chart" aria-label="This week sales and purchases">
                    @foreach ($weeklySalesPurchases as $day)
                        <div class="flux-bar-day">
                            <div class="flux-bar-pair">
                                <div class="flux-bar flux-bar--sales" style="height: {{ $day['salesHeight'] }}%" title="Sales {{ app_money($day['sales']) }}"></div>
                                <div class="flux-bar flux-bar--purchase" style="height: {{ $day['purchasesHeight'] }}%" title="Purchases {{ app_money($day['purchases']) }}"></div>
                            </div>
                            <span>{{ $day['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </section>

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

        <div class="flux-dashboard-grid flux-dashboard-grid--two">
            <section class="flux-widget flux-widget--pie">
                <div class="flux-widget__header">
                    <div>
                        <h2>Top Selling Categories</h2>
                        <p>This week</p>
                    </div>
                </div>

                <div class="flux-pie-layout">
                    <div class="flux-pie-chart" style="--flux-pie: conic-gradient({{ $topCategories['gradient'] }});">
                        <span>{{ app_money($topCategories['total']) }}</span>
                    </div>

                    <div class="flux-pie-legend">
                        @forelse ($topCategories['slices'] as $slice)
                            <div class="flux-pie-legend__item">
                                <span style="--slice-color: {{ $slice['color'] }}"></span>
                                <div>
                                    <strong>{{ $slice['label'] }}</strong>
                                    <small>{{ $slice['percentage'] }}% - {{ app_money($slice['value']) }}</small>
                                </div>
                            </div>
                        @empty
                            <p class="flux-empty">No sales this week.</p>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="flux-widget flux-widget--pie">
                <div class="flux-widget__header">
                    <div>
                        <h2>Top Customers</h2>
                        <p>This week</p>
                    </div>
                </div>

                <div class="flux-pie-layout">
                    <div class="flux-pie-chart" style="--flux-pie: conic-gradient({{ $topCustomers['gradient'] }});">
                        <span>{{ app_money($topCustomers['total']) }}</span>
                    </div>

                    <div class="flux-pie-legend">
                        @forelse ($topCustomers['slices'] as $slice)
                            <div class="flux-pie-legend__item">
                                <span style="--slice-color: {{ $slice['color'] }}"></span>
                                <div>
                                    <strong>{{ $slice['label'] }}</strong>
                                    <small>{{ $slice['percentage'] }}% - {{ app_money($slice['value']) }}</small>
                                </div>
                            </div>
                        @empty
                            <p class="flux-empty">No customer sales this week.</p>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>

        <div class="flux-dashboard-grid flux-dashboard-grid--two">
            <section class="flux-widget">
                <div class="flux-widget__header">
                    <div>
                        <h2>Top Selling Products</h2>
                        <p>This week</p>
                    </div>
                </div>

                <div class="flux-table-wrap">
                    <table class="flux-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th class="flux-table__number">Qty</th>
                                <th class="flux-table__number">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($topProducts as $product)
                                <tr>
                                    <td>{{ $product['name'] }}</td>
                                    <td class="flux-table__number">{{ number_format($product['quantity'], 2) }}</td>
                                    <td class="flux-table__number">{{ app_money($product['amount']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="flux-empty">No product sales this week.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="flux-widget">
                <div class="flux-widget__header">
                    <div>
                        <h2>Stock Alerts</h2>
                        <p>Items at or below alert level</p>
                    </div>
                </div>

                <div class="flux-table-wrap">
                    <table class="flux-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th class="flux-table__number">Stock</th>
                                <th class="flux-table__number">Alert</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($stockAlerts as $item)
                                <tr>
                                    <td>{{ $item['name'] }}</td>
                                    <td class="flux-table__number">{{ number_format($item['currentStock'], 2) }}</td>
                                    <td class="flux-table__number">{{ number_format($item['alertQty'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="flux-empty">No stock alerts.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-filament-panels::page>
