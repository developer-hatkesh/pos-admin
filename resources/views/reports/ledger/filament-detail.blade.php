<x-filament-panels::page>
    @include('reports.ledger.detail-body', [
        'company' => $party->company,
        'party' => $party,
        'partyType' => $partyType,
        'title' => $title,
        'summary' => $summary,
        'rows' => $rows,
        'fromDate' => $fromDate,
        'toDate' => $toDate,
        'showPrintButton' => false,
    ])
</x-filament-panels::page>
