@php
    $money = fn (float|int|string|null $amount): string => app_money($amount);
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>VAT Report</title>
    <style>
        body { color: #111827; font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
        h1 { font-size: 24px; margin: 0; }
        h2 { font-size: 15px; margin: 22px 0 8px; }
        .sub { color: #4b5563; margin-top: 5px; }
        .grid { display: grid; gap: 10px; grid-template-columns: repeat(4, 1fr); margin-top: 16px; }
        .card { border: 1px solid #d1d5db; padding: 10px; }
        .label { color: #4b5563; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .value { font-size: 16px; font-weight: 700; margin-top: 4px; }
        table { border-collapse: collapse; margin-top: 8px; width: 100%; }
        th, td { border: 1px solid #d1d5db; padding: 6px; }
        th { background: #f3f4f6; text-align: left; }
        .num { text-align: right; }
        .empty { color: #6b7280; text-align: center; }
        @media print { body { margin: 0; } .no-print { display: none; } }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Print</button>

    <h1>VAT Report</h1>
    <div class="sub">
        {{ $report['company']?->name ?? config('app.name') }} |
        {{ $report['start_date']->format('d-M-Y') }} to {{ $report['end_date']->format('d-M-Y') }} |
        Generated {{ $report['generated_at']->format('d-M-Y h:i A') }}
    </div>

    <div class="grid">
        @foreach ([1, 4, 5, 6] as $box)
            <div class="card">
                <div class="label">Box {{ $box }}</div>
                <div class="value">{{ $money($report['boxes'][$box]['amount']) }}</div>
            </div>
        @endforeach
    </div>

    <h2>HMRC VAT Boxes</h2>
    <table>
        <tbody>
            @foreach ($report['boxes'] as $box => $data)
                <tr>
                    <td><strong>Box {{ $box }}</strong></td>
                    <td>{{ $data['label'] }}</td>
                    <td class="num"><strong>{{ $money($data['amount']) }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @foreach ($report['sections'] as $section)
        <h2>{{ $section['title'] }}</h2>
        @isset($section['note'])
            <div class="sub">{{ $section['note'] }}</div>
        @endisset
        <table>
            <thead>
                <tr>
                    <th>Date</th><th>Type</th><th>Number</th><th>Party / Category</th><th class="num">Net</th><th class="num">VAT</th><th class="num">Gross</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($section['rows'] as $row)
                    <tr>
                        <td>{{ optional($row['date'])->format('d-M-Y') }}</td>
                        <td>{{ $row['type'] }}</td>
                        <td>{{ $row['number'] }}</td>
                        <td>{{ $row['party'] }}</td>
                        <td class="num">{{ $money($row['net']) }}</td>
                        <td class="num">{{ $money($row['vat']) }}</td>
                        <td class="num">{{ $money($row['gross']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">No records found for this section.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="num"><strong>Total ({{ $section['summary']['count'] }} rows)</strong></td>
                    <td class="num"><strong>{{ $money($section['summary']['net']) }}</strong></td>
                    <td class="num"><strong>{{ $money($section['summary']['vat']) }}</strong></td>
                    <td class="num"><strong>{{ $money($section['summary']['gross']) }}</strong></td>
                </tr>
            </tfoot>
        </table>
    @endforeach

    <h2>Payment Summary</h2>
    <table>
        <tbody>
            <tr><td>Receipts</td><td class="num">{{ $money($report['payments']['receipts']) }}</td></tr>
            <tr><td>Payments</td><td class="num">{{ $money($report['payments']['payments']) }}</td></tr>
            <tr><td>Net Cash Flow</td><td class="num">{{ $money($report['payments']['net_cash_flow']) }}</td></tr>
        </tbody>
    </table>
</body>
</html>
