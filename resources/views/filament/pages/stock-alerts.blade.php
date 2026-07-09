<x-filament-panels::page>
    <div class="flux-dashboard">
        <div class="flux-dashboard__header">
            <div>
                <h1 class="flux-dashboard__title">Stock Alerts</h1>
            </div>
            <div class="flux-dashboard__date">
                {{ now()->format('D, d M Y') }}
            </div>
        </div>

        <div class="flux-dashboard-grid">
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
