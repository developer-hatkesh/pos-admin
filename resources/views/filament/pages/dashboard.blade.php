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
    </div>
</x-filament-panels::page>
