@include('reports.ledger.partials.styles')
@php
    $isBank = $partyType === 'Bank';
    $code = $isBank ? $party->account_number : ($partyType === 'Customer' ? $party->customer_code : $party->supplier_code);
    $address = $isBank ? null : collect([$party->billing_address ?? $party->address ?? null, $party->address_line1, $party->address_line2, $party->city, $party->postcode, $party->country])->filter()->implode(', ');
@endphp

<div class="report">
    @include('reports.ledger.partials.header', ['company' => $company, 'title' => $title, 'fromDate' => $fromDate, 'toDate' => $toDate])

    @if ($isBank)
        <div class="summary">
            <div><span>Bank Name</span><span>:</span><span>{{ $party->bank_name }}</span></div>
            <div><span>Account Name</span><span>:</span><span>{{ $party->account_name }}</span></div>
            <div><span>Account Number</span><span>:</span><span>{{ $party->account_number ?? '-' }}</span></div>
            <div><span>Bank Code</span><span>:</span><span>{{ $party->sort_code ?? '-' }}</span></div>
            <div><span>Ledger Group</span><span>:</span><span>{{ $party->ledger?->parent?->name ?? $party->ledger?->name ?? '-' }}</span></div>
            <div><span>Opening Balance</span><span>:</span><span>{{ $summary['opening_formatted'] }}</span></div>
            <div><span>Closing Balance</span><span>:</span><span>{{ $summary['closing_formatted'] }}</span></div>
        </div>
    @else
        <div class="summary">
            <div><span>Ledger Name</span><span>:</span><span>{{ $party->name }}</span></div>
            <div><span>Ledger Code</span><span>:</span><span>{{ $code }}</span></div>
            <div><span>Ledger Type</span><span>:</span><span>{{ $partyType }}</span></div>
            <div><span>Group</span><span>:</span><span>{{ $party->ledger?->parent?->name ?? $party->ledger?->name ?? '-' }}</span></div>
            <div><span>Phone</span><span>:</span><span>{{ $party->phone ?? '-' }}</span></div>
            <div><span>Email</span><span>:</span><span>{{ $party->email ?? '-' }}</span></div>
            <div><span>Address</span><span>:</span><span>{{ $address ?: '-' }}</span></div>
            <div><span>Opening Balance</span><span>:</span><span>{{ $summary['opening_formatted'] }}</span></div>
            <div><span>Closing Balance</span><span>:</span><span>{{ $summary['closing_formatted'] }}</span></div>
        </div>
    @endif

    <table>
        <thead>
            <tr>
                <th style="width: 10%">Date</th>
                <th style="width: 12%">Voucher No.</th>
                <th style="width: 14%">Voucher Type</th>
                <th>Particulars</th>
                <th style="width: 12%">Debit</th>
                <th style="width: 12%">Credit</th>
                <th style="width: 12%">Balance</th>
                <th style="width: 7%">Dr/Cr</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td></td><td></td><td></td>
                <td class="strong">Opening Balance</td>
                <td class="num"></td><td class="num"></td>
                <td class="num">{{ \App\Services\Reports\CurrencyService::format(abs($summary['opening'])) }}</td>
                <td class="center">{{ $summary['opening'] === 0.0 ? '' : ($summary['opening'] > 0 ? 'Dr' : 'Cr') }}</td>
            </tr>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ optional($row['date'])->format('d-M-Y') }}</td>
                    <td>{{ $row['voucher_no'] }}</td>
                    <td>{{ $row['voucher_type'] }}</td>
                    <td>{{ $row['particulars'] }}</td>
                    <td class="num">{{ $row['debit'] > 0 ? \App\Services\Reports\CurrencyService::format($row['debit']) : '-' }}</td>
                    <td class="num">{{ $row['credit'] > 0 ? \App\Services\Reports\CurrencyService::format($row['credit']) : '-' }}</td>
                    <td class="num">{{ \App\Services\Reports\CurrencyService::format(abs($row['balance'])) }}</td>
                    <td class="center">{{ $row['dr_cr'] }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="center">Total</td>
                <td class="num">{{ \App\Services\Reports\CurrencyService::format($summary['debit']) }}</td>
                <td class="num">{{ \App\Services\Reports\CurrencyService::format($summary['credit']) }}</td>
                <td class="num">{{ \App\Services\Reports\CurrencyService::format(abs($summary['closing'])) }}</td>
                <td class="center">{{ $summary['dr_cr'] }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <div>* Dr = Debit (You Pay) &nbsp;&nbsp;&nbsp; Cr = Credit (You Owe)</div>
        <div>This is a computer generated report.</div>
    </div>
</div>
