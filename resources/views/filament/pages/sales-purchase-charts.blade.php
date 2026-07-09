<x-filament-panels::page>
    <div class="flux-dashboard">
        <div class="flux-dashboard__header">
            <div>
                <h1 class="flux-dashboard__title">Sales & Purchase Charts</h1>
            </div>
            <div class="flux-dashboard__date">
                {{ now()->format('D, d M Y') }}
            </div>
        </div>

        <div class="flux-dashboard-grid">
            <section class="flux-widget">
                <div class="flux-widget__header">
                    <div>
                        <h2>This Month Sales & Purchases</h2>
                        <p>Weekly invoice counts and totals</p>
                    </div>
                    <div class="flux-widget__legend">
                        <span><i class="flux-dot flux-dot--sales"></i>Sales</span>
                        <span><i class="flux-dot flux-dot--purchase"></i>Purchases</span>
                    </div>
                </div>

                <div class="flux-bar-chart" aria-label="This month weekly sales and purchases">
                    @foreach ($weeklySalesPurchases as $week)
                        <div class="flux-bar-day">
                            <div class="flux-bar-pair">
                                <div class="flux-bar flux-bar--sales" style="height: {{ $week['salesHeight'] }}%" title="Sales: {{ number_format($week['salesCount']) }} invoices, {{ app_money($week['sales']) }}"></div>
                                <div class="flux-bar flux-bar--purchase" style="height: {{ $week['purchasesHeight'] }}%" title="Purchases: {{ number_format($week['purchasesCount']) }} invoices, {{ app_money($week['purchases']) }}"></div>
                            </div>
                            <span>{{ $week['label'] }}</span>
                        </div>
                    @endforeach
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

        <div class="flux-dashboard-grid">
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
        </div>
    </div>
</x-filament-panels::page>
