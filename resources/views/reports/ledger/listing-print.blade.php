<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    @include('reports.ledger.partials.styles')
</head>
<body>
    <div class="print-actions"><button type="button" onclick="window.print()">Print</button></div>
    <div class="report">
        @include('reports.ledger.partials.header', ['company' => $company, 'title' => $title, 'fromDate' => $fromDate, 'toDate' => $toDate])
        <table style="margin-top: 18px">
            <thead>
                <tr>
                    <th>{{ $partyType }} Name</th>
                    <th>{{ $partyType }} Code</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Opening Balance</th>
                    <th>Total Debit</th>
                    <th>Total Credit</th>
                    <th>Closing Balance</th>
                    <th>Dr/Cr</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    @php($party = $row['party'])
                    @php($summary = $row['summary'])
                    <tr>
                        <td>{{ $party->name }}</td>
                        <td>{{ $partyType === 'Customer' ? $party->customer_code : $party->supplier_code }}</td>
                        <td>{{ $party->phone }}</td>
                        <td>{{ $party->email }}</td>
                        <td class="num">{{ $summary['opening_formatted'] }}</td>
                        <td class="num">{{ \App\Services\Reports\CurrencyService::format($summary['debit']) }}</td>
                        <td class="num">{{ \App\Services\Reports\CurrencyService::format($summary['credit']) }}</td>
                        <td class="num">{{ $summary['closing_formatted'] }}</td>
                        <td class="center">{{ $summary['dr_cr'] }}</td>
                        <td>{{ $party->status?->value ?? $party->status }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="footer">
            <div>* Dr = Debit Balance &nbsp;&nbsp;&nbsp; Cr = Credit Balance</div>
            <div>This is a computer generated report.</div>
        </div>
    </div>
</body>
</html>
