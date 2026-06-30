@php
    $money = fn (float|int|string|null $amount): string => \App\Services\Reports\CurrencyService::format($amount);
    $rows = [
        ['Total Sale', $money($report['sales']['total'])],
        ['Cash', $money($report['sales']['cash'])],
        ['Credit', $money($report['sales']['credit'])],
        ['Bank Transfer', $money($report['sales']['bank_transfer'])],
        ['Card Payment', $money($report['sales']['card_payment'])],
        ['Expenses', $money($report['outgoings']['expenses'])],
        ['Wages', $money($report['outgoings']['wages'])],
    ];
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Summary Report</title>
    <style>
        body { color: #111827; font-family: Arial, sans-serif; font-size: 13px; margin: 24px; }
        .report { border: 1px solid #111827; margin: 0 auto; max-width: 760px; }
        .header { border-bottom: 1px solid #111827; padding: 18px 22px; }
        h1 { font-size: 24px; margin: 0; text-transform: uppercase; }
        .sub { color: #4b5563; margin-top: 6px; }
        .row { align-items: center; border-bottom: 1px solid #111827; display: flex; justify-content: space-between; min-height: 34px; padding: 8px 22px; }
        .label { font-weight: 700; letter-spacing: .02em; text-transform: uppercase; }
        .value { font-size: 16px; font-weight: 700; }
        .qty { display: block; padding: 14px 22px; }
        .qty .value { display: block; font-size: 28px; margin-top: 8px; }
        .bank-wrap { background: #f9fafb; padding: 18px 22px 22px; }
        .bank-title { font-weight: 700; margin-bottom: 10px; text-transform: uppercase; }
        .bank { background: #fff; border: 1px solid #d1d5db; margin-top: 8px; padding: 12px; }
        @media print { body { margin: 0; } .report { border-width: 1px; max-width: none; } }
    </style>
</head>
<body>
    <div class="report">
        <div class="header">
            <h1>Summary Report</h1>
            <div class="sub">
                {{ $report['company']?->name ?? config('app.name') }} |
                {{ $report['start_date']->format('d-M-Y') }} to {{ $report['end_date']->format('d-M-Y') }} |
                Generated {{ $report['generated_at']->format('d-M-Y h:i A') }}
            </div>
        </div>

        @foreach ($rows as [$label, $value])
            <div class="row">
                <div class="label">{{ $label }}</div>
                <div class="value">{{ $value }}</div>
            </div>
        @endforeach

        <div class="row qty">
            <div class="label">Total Quantity of Perfume Today Sold Out</div>
            <div class="value">{{ number_format((float) $report['stock']['perfume_qty_sold'], 3) }}</div>
        </div>

        <div class="row">
            <div class="label">Total Cash</div>
            <div class="value">{{ $money($report['sales']['cash']) }}</div>
        </div>

        <div class="row">
            <div class="label">Total Credit</div>
            <div class="value">{{ $money($report['sales']['credit']) }}</div>
        </div>

        <div class="bank-wrap">
            <div class="bank-title">Bank Balances</div>
            @forelse ($report['bank_balances'] as $index => $bank)
                <div class="bank">
                    <div class="label">Total Amount in {{ $bank['name'] !== '' ? $bank['name'] : 'Bank '.($index + 1) }}</div>
                    <div class="value">{{ $money($bank['amount']) }}</div>
                </div>
            @empty
                <div>No bank accounts found.</div>
            @endforelse
        </div>
    </div>
</body>
</html>
