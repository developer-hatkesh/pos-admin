@php
    $summary = $report['summary'];
    $money = fn (float|int|string|null $amount): string => \App\Services\Reports\CurrencyService::format($amount);
@endphp

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Balance Sheet</title>
    @include('reports.ledger.partials.styles')
    <style>
        .balance-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 18px; }
        .statement { border: 1px solid #d7dee8; }
        .statement-title { color: #fff; padding: 11px 12px; font-size: 16px; font-weight: 700; text-transform: uppercase; }
        .assets-title { background: #047857; }
        .liabilities-title { background: #b91c1c; }
        .group-row { background: #f1f5f9; font-weight: 700; }
        .category-row { font-weight: 700; }
        .ledger-row td:first-child { padding-left: 24px; color: #334155; }
        .total-row { color: #fff; font-weight: 700; }
        .asset-total { background: #065f46; }
        .liability-total { background: #1d4ed8; }
        .balanced { margin-top: 16px; padding: 10px 12px; border: 1px solid #86efac; background: #f0fdf4; color: #166534; font-weight: 700; }
        .unbalanced { margin-top: 16px; padding: 10px 12px; border: 1px solid #fca5a5; background: #fef2f2; color: #991b1b; font-weight: 700; }
    </style>
</head>
<body>
    <div class="print-actions"><button type="button" onclick="window.print()">Print</button></div>
    <div class="report">
        @include('reports.ledger.partials.header', [
            'company' => $report['company'],
            'title' => 'Balance Sheet',
            'fromDate' => null,
            'toDate' => $report['as_on_date']->toDateString(),
        ])

        <div class="balance-grid">
            @foreach (['assets', 'liabilities_equity'] as $side)
                @php($section = $report['sections'][$side])
                <div class="statement">
                    <div class="statement-title {{ $side === 'assets' ? 'assets-title' : 'liabilities-title' }}">{{ $section['title'] }}</div>
                    <table>
                        <tbody>
                            @foreach ($section['groups'] as $group)
                                <tr class="group-row">
                                    <td>{{ $group['name'] }}</td>
                                    <td class="num">{{ $money($group['amount']) }}</td>
                                </tr>
                                @foreach ($group['categories'] as $category)
                                    <tr class="category-row">
                                        <td>{{ $category['name'] }}</td>
                                        <td class="num">{{ $money($category['amount']) }}</td>
                                    </tr>
                                    @foreach ($category['ledgers'] as $ledger)
                                        <tr class="ledger-row">
                                            <td>{{ trim(($ledger['code'] ? $ledger['code'].' - ' : '').$ledger['name']) }}</td>
                                            <td class="num">{{ $money($ledger['statement_amount']) }}</td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            @endforeach
                            <tr class="total-row {{ $side === 'assets' ? 'asset-total' : 'liability-total' }}">
                                <td>{{ $side === 'assets' ? 'Total Assets' : 'Total Liabilities + Equity' }}</td>
                                <td class="num">{{ $money($side === 'assets' ? $summary['total_assets'] : $summary['total_liabilities_equity']) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>

        <div class="{{ $summary['is_balanced'] ? 'balanced' : 'unbalanced' }}">
            @if ($summary['is_balanced'])
                Balance Sheet Balanced
            @else
                Balance Sheet not balanced. Difference: {{ $money($summary['difference']) }}
            @endif
        </div>

        <div class="footer">
            <div>Assets = Liabilities + Equity</div>
            <div>This is a computer generated report.</div>
        </div>
    </div>
</body>
</html>
