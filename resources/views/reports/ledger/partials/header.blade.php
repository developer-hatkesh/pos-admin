@php
    $rangeText = $fromDate || $toDate
        ? trim(($fromDate ? \Illuminate\Support\Carbon::parse($fromDate)->format('d-M-Y') : 'Beginning').' To '.($toDate ? \Illuminate\Support\Carbon::parse($toDate)->format('d-M-Y') : 'Today'))
        : 'All Dates';
@endphp

<div class="header">
    <div class="brand">
        <div class="logo">{{ strtoupper(substr((string) ($company?->name ?? 'C'), 0, 1)) }}</div>
        <div>
            <div class="company-name">{{ $company?->name ?? config('app.name') }}</div>
            <div class="muted">
                {{ $company?->address }}{{ $company?->city ? ', '.$company->city : '' }}{{ $company?->postcode ? ' - '.$company->postcode : '' }}<br>
                Phone: {{ $company?->phone ?? '-' }} | Email: {{ $company?->email ?? '-' }}
            </div>
        </div>
    </div>
    <div class="title">
        <h1>{{ $title }}</h1>
        <div class="range">{{ $rangeText }}</div>
    </div>
    <div class="meta">
        <div><span>Date</span><span>:</span><span>{{ now()->format('d-M-Y') }}</span></div>
        <div><span>Time</span><span>:</span><span>{{ now()->format('h:i A') }}</span></div>
        <div><span>Page</span><span>:</span><span>1 of 1</span></div>
    </div>
</div>
