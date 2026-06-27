<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    @include('reports.ledger.partials.styles')
</head>
<body>
    <div class="print-actions"><button type="button" onclick="window.print()">Print</button></div>
    @include('reports.ledger.detail-body', [
        'company' => $company,
        'party' => $party,
        'partyType' => $partyType,
        'title' => $title,
        'summary' => $summary,
        'rows' => $rows,
        'fromDate' => $fromDate,
        'toDate' => $toDate,
    ])
</body>
</html>
