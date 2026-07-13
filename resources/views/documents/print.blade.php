<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} {{ $document->{$numberColumn} }}</title>
    <style>
        * { box-sizing: border-box; }
        :root { color: #0f172a; font-family: Arial, Helvetica, sans-serif; font-size: 14px; --primary: #1e40af; --muted: #64748b; --border: #e2e8f0; }
        body { margin: 0; background: #f8fafc; }
        .actions { display: flex; justify-content: flex-end; gap: 8px; width: min(210mm, calc(100% - 32px)); margin: 16px auto 0; }
        .actions a, .actions button { border: 0; border-radius: 6px; padding: 10px 16px; color: #fff; background: var(--primary); font-weight: 700; text-decoration: none; cursor: pointer; }
        .actions a { background: var(--muted); }
        .page { width: min(210mm, calc(100% - 32px)); min-height: 297mm; margin: 16px auto; padding: 18mm; background: #fff; border-top: 6px solid var(--primary); box-shadow: 0 20px 60px rgba(15, 23, 42, .12); }
        .header, .meta, .summary-row { display: flex; justify-content: space-between; gap: 24px; }
        .header { align-items: flex-start; border-bottom: 2px solid var(--primary); padding-bottom: 18px; }
        h1 { margin: 0; color: var(--primary); font-size: 32px; } h2 { margin: 0 0 8px; font-size: 16px; } p { margin: 3px 0; }
        .muted { color: var(--muted); } .meta { margin: 28px 0; } .box { min-width: 0; } .right { text-align: right; }
        table { width: 100%; border-collapse: collapse; } th { border-bottom: 2px solid var(--primary); padding: 10px 8px; text-align: left; color: var(--primary); font-size: 12px; text-transform: uppercase; } td { border-bottom: 1px solid var(--border); padding: 10px 8px; vertical-align: top; }
        .text-right { text-align: right; } .totals { width: min(320px, 100%); margin: 28px 0 0 auto; } .summary-row { border-bottom: 1px solid var(--border); padding: 8px 0; } .total { border-bottom: 2px solid var(--primary); color: var(--primary); font-size: 17px; font-weight: 800; }
        .notes { margin-top: 32px; padding: 14px; border-left: 4px solid var(--primary); background: #eff6ff; }
        @media print { body { background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; } .actions { display: none; } .page { width: auto; min-height: auto; margin: 0; padding: 0; box-shadow: none; } @page { size: A4 portrait; margin: 14mm; } }
    </style>
</head>
<body>
@php
    $company = $document->company;
    $party = $document->{$partyRelation};
    $status = $document->status instanceof \BackedEnum ? $document->status->value : (string) $document->status;
    $address = $party?->billing_address ?: ($party?->address ?: collect([$party?->address_line1, $party?->address_line2])->filter()->join(', '));
@endphp
<div class="actions"><a href="{{ url()->previous() }}">Back</a><button type="button" onclick="window.print()">Print</button></div>
<main class="page">
    <header class="header">
        <div><h1>{{ $title }}</h1><p class="muted">{{ $document->{$numberColumn} }}</p></div>
        <div class="box right"><h2>{{ $company?->name ?: 'Company' }}</h2><p>{{ $company?->address }}</p><p>{{ collect([$company?->city, $company?->postcode])->filter()->join(', ') }}</p><p>{{ $company?->phone }}</p><p>{{ $company?->email }}</p>@if($company?->vat_number)<p>VAT: {{ $company->vat_number }}</p>@endif</div>
    </header>
    <section class="meta">
        <div class="box"><h2>{{ $partyRelation === 'supplier' ? 'Supplier' : 'Customer' }}</h2><p><strong>{{ $party?->company_name ?: ($party?->name ?: 'N/A') }}</strong></p><p>{{ $address }}</p><p>{{ collect([$party?->city, $party?->postcode])->filter()->join(', ') }}</p><p>{{ $party?->phone ?: $party?->mobile_no }}</p><p>{{ $party?->email }}</p></div>
        <div class="box right"><p><strong>Date:</strong> {{ $document->{$dateColumn}?->format('d M Y') }}</p>@if($dueDateColumn && $document->{$dueDateColumn})<p><strong>{{ $title === 'Estimate' ? 'Expiry' : 'Due' }}:</strong> {{ $document->{$dueDateColumn}->format('d M Y') }}</p>@endif<p><strong>Status:</strong> {{ ucfirst($status) }}</p></div>
    </section>
    <table><thead><tr><th>Description</th><th class="text-right">Qty</th><th class="text-right">Rate</th><th class="text-right">VAT</th><th class="text-right">Line Total</th></tr></thead><tbody>
    @foreach($document->items as $item)<tr><td><strong>{{ $item->description ?: $item->productItem?->name }}</strong>@if($item->productItem?->item_code)<p class="muted">Code: {{ $item->productItem->item_code }}</p>@endif</td><td class="text-right">{{ rtrim(rtrim(number_format((float) $item->qty, 3), '0'), '.') }}</td><td class="text-right">{{ app_money((float) $item->rate) }}</td><td class="text-right">{{ app_money((float) $item->vat_amount) }}</td><td class="text-right">{{ app_money((float) $item->line_total) }}</td></tr>@endforeach
    </tbody></table>
    <section class="totals"><div class="summary-row"><span>Subtotal</span><span>{{ app_money((float) $document->subtotal) }}</span></div>@if(isset($document->discount))<div class="summary-row"><span>Discount</span><span>{{ app_money((float) $document->discount) }}</span></div>@endif<div class="summary-row"><span>VAT</span><span>{{ app_money((float) $document->vat_total) }}</span></div><div class="summary-row total"><span>Total</span><strong>{{ app_money((float) $document->total) }}</strong></div>@if($paid !== null)<div class="summary-row"><span>Paid</span><span>{{ app_money($paid) }}</span></div><div class="summary-row total"><span>Amount Due</span><strong>{{ app_money((float) $due) }}</strong></div>@endif</section>
    @if(filled($document->notes))<section class="notes"><strong>Notes</strong><p>{{ $document->notes }}</p></section>@endif
</main>
</body>
</html>
