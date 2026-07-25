<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Credit Note - {{ $invoice->return_no }}</title>
    <style>
        * { box-sizing: border-box; }
        :root { --primary: #003399; --border: #d1d5db; --muted: #6b7280; --soft: #eff6ff; --text: #333; }
        html, body { margin: 0; color: var(--text); background: #f3f4f6; font: 10pt/1.45 Arial, Helvetica, sans-serif; }
        .toolbar { position: sticky; top: 0; z-index: 10; display: flex; justify-content: space-between; padding: 10px 18px; background: #fff; border-bottom: 1px solid var(--border); }
        .toolbar-title { color: var(--primary); font-weight: 700; text-transform: uppercase; }
        .toolbar-actions { display: flex; gap: 8px; }
        .toolbar a, .toolbar button { padding: 8px 14px; color: #fff; background: var(--primary); border: 0; border-radius: 4px; font-weight: 700; text-decoration: none; cursor: pointer; }
        .toolbar a { color: var(--primary); background: var(--soft); }
        .sheet { display: flex; flex-direction: column; width: min(1000px, calc(100% - 32px)); min-height: 297mm; margin: 18px auto; padding: 9mm; background: #fff; border: 1px solid #e5e7eb; box-shadow: 0 4px 14px rgba(0,0,0,.1); }
        .header { display: flex; justify-content: space-between; align-items: flex-start; gap: 25px; margin-bottom: 8mm; }
        .identity { display: flex; gap: 14px; }
        .logo { width: 64px; height: 64px; object-fit: contain; }
        .company-name { color: var(--primary); font-size: 18pt; font-weight: 700; }
        .company-details { margin-top: 4px; }
        .label { color: var(--primary); font-weight: 700; }
        h1 { margin: 0 0 4mm; color: var(--primary); font-size: 28pt; line-height: 1; text-align: right; }
        .meta { border-collapse: collapse; font-size: 9.5pt; }
        .meta td { padding: 2px 0 2px 12px; }
        .meta td:first-child { padding-left: 0; }
        .meta-value { font-weight: 600; }
        .panels, .summary-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 8mm; }
        .panels { margin-bottom: 8mm; }
        .card { padding: 4mm; border: 1px solid var(--border); border-radius: 4px; }
        .card-title { margin-bottom: 6px; color: var(--primary); font-weight: 700; }
        .party-name { margin-bottom: 3px; font-size: 11pt; font-weight: 700; }
        .muted { color: var(--muted); }
        .items { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .items th { padding: 7px 8px; color: #fff; background: var(--primary); border: 1px solid var(--border); font-weight: 600; }
        .items td { padding: 9px 8px; border: 1px solid var(--border); vertical-align: top; }
        .items .serial { width: 6%; } .items .description { width: 34%; } .items .qty { width: 8%; } .items .money { width: 13%; }
        .text-center { text-align: center; } .text-right { text-align: right; }
        .item-name { font-weight: 600; }
        .item-meta { color: var(--muted); font-size: 8.5pt; }
        .summary-layout { align-items: start; margin: 8mm 0; }
        .summary { border: 1px solid var(--border); border-radius: 4px; }
        .summary-row { display: flex; justify-content: space-between; padding: 6px 12px; border-bottom: 1px solid #e5e7eb; }
        .summary-row:last-child { border-bottom: 0; }
        .summary-row strong { font-weight: 700; }
        .summary-row.total { color: var(--primary); background: #f8fbff; font-weight: 700; }
        .notes { margin-top: auto; }
        .footer { margin-top: 4mm; padding: 4mm 0; color: var(--muted); border-top: 1px solid #e5e7eb; font-size: 8pt; text-align: center; text-transform: uppercase; letter-spacing: .4px; }
        @media (max-width: 700px) { .sheet { width: 100%; margin: 0; padding: 15px; } .header { display: block; } h1 { margin-top: 20px; text-align: left; } .panels, .summary-layout { grid-template-columns: 1fr; } }
        @media print { @page { size: A4 portrait; margin: 0; } html, body { background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; } .toolbar { display: none; } .sheet { width: 210mm; min-height: 297mm; margin: 0; border: 0; box-shadow: none; } }
    </style>
</head>
<body>
@php
    $company = $invoice->company;
    $customer = $invoice->customer;
    $billingAddress = $customer?->billing_address ?: collect([$customer?->address_line1, $customer?->address_line2, $customer?->city, $customer?->postcode, $customer?->country])->filter()->join(', ');
    $shippingAddress = $customer?->delivery_address ?: $billingAddress;
    $customerVat = $customer?->tax_number ?: $customer?->vat_number;
    $customerPhone = $customer?->phone ?: ($customer?->telephone_no ?: $customer?->mobile_no);
    $subtotal = (float) $invoiceTotals['subtotal'];
    $discount = (float) ($invoiceTotals['discount'] ?? 0);
    $vatTotal = (float) $invoiceTotals['vat_total'];
    $grandTotal = (float) $invoiceTotals['total'];
    $companyNote = trim((string) ($company?->notes ?? ''));
    $originalInvoices = $invoice->salesInvoices;
    if ($originalInvoices->isEmpty() && $invoice->salesInvoice) { $originalInvoices = collect([$invoice->salesInvoice]); }
    $originalNumbers = $originalInvoices->pluck('invoice_no')->filter()->join(', ');
    $originalDates = $originalInvoices->map(fn ($sale) => $sale->invoice_date?->format('d M Y'))->filter()->unique()->join(', ');
    $money = fn (float|int|string|null $amount): string => \App\Support\CurrencyFormatter::formatForCurrency($amount, $invoice->currency_id ?: $customer?->currency_id);
@endphp
<header class="toolbar"><div class="toolbar-title">Credit Note</div><div class="toolbar-actions"><a href="{{ url()->previous() }}">Back</a><button type="button" onclick="window.print()">Print Credit Note</button></div></header>
<main class="sheet">
    <header class="header">
        <div class="identity">
            @if(filled($logoUrl))<img class="logo" src="{{ $logoUrl }}" alt="{{ $company?->name ?: 'Company' }} logo">@endif
            <div><div class="company-name">{{ $company?->legal_business_name ?: ($company?->name ?: 'Company') }}</div><div class="company-details">@if($company?->address)<div>{{ $company->address }}</div>@endif @if($company?->city || $company?->postcode)<div>{{ collect([$company?->city, $company?->postcode])->filter()->join(', ') }}</div>@endif @if($company?->country)<div>{{ $company->country }}</div>@endif @if($company?->vat_number)<div><strong>VAT No:</strong> {{ $company->vat_number }}</div>@endif @if($company?->company_house_number)<div><strong>Company No:</strong> {{ $company->company_house_number }}</div>@endif @if($company?->email)<div><strong>Email:</strong> {{ $company->email }}</div>@endif @if($company?->business_phone_number || $company?->phone)<div><strong>Phone:</strong> {{ $company?->business_phone_number ?: $company?->phone }}</div>@endif</div></div>
        </div>
        <div><h1>CREDIT NOTE</h1><table class="meta"><tr><td>Credit Note No</td><td class="meta-value">: {{ $invoice->return_no }}</td></tr><tr><td>Credit Note Date</td><td class="meta-value">: {{ $invoice->return_date?->format('d M Y') ?: '—' }}</td></tr><tr><td>Original Invoice No</td><td class="meta-value">: {{ $originalNumbers ?: '—' }}</td></tr><tr><td>Original Invoice Date</td><td class="meta-value">: {{ $originalDates ?: '—' }}</td></tr><tr><td>Reference</td><td class="meta-value">: {{ $invoice->reference ?? '—' }}</td></tr></table></div>
    </header>
    <section class="panels">
        <div class="card"><div class="card-title">Credit Note To</div><div class="party-name">{{ $customer?->company_name ?: ($customer?->name ?: 'Walk-in Customer') }}</div>@if($billingAddress)<div class="muted">{{ $billingAddress }}</div>@endif @if($customer?->email)<div class="muted">{{ $customer->email }}</div>@endif @if($customerVat)<div><span class="label">VAT No:</span> <span class="muted">{{ $customerVat }}</span></div>@endif</div>
        <div class="card"><div class="card-title">Ship To</div><div class="party-name">{{ $customer?->company_name ?: ($customer?->name ?: 'Walk-in Customer') }}</div>@if($shippingAddress)<div class="muted">{{ $shippingAddress }}</div>@endif @if($customer?->email)<div class="muted">{{ $customer->email }}</div>@endif @if($customerPhone)<div class="muted">{{ $customerPhone }}</div>@endif</div>
    </section>
    <table class="items"><thead><tr><th class="serial">Sr</th><th class="description text-left">Description</th><th class="qty">Qty</th><th class="money text-right">Unit Price</th><th class="money text-right">Discount</th><th class="money text-right">Tax</th><th class="money text-right">Line Total</th></tr></thead><tbody>
        @forelse($invoice->items as $item)
            @php $calculated = $invoiceTotals['items'][$loop->index] ?? []; $gross = round((float) $item->qty * (float) $item->rate, 2); $lineDiscount = $subtotal > 0 ? round($discount * $gross / $subtotal, 2) : 0; $tax = (float) ($calculated['vat_amount'] ?? $item->vat_amount); $lineTotal = max(0, $gross - $lineDiscount) + $tax; @endphp
            <tr><td class="text-center">{{ $loop->iteration }}</td><td><div class="item-name">{{ $item->description ?: ($item->productItem?->name ?: 'Item') }}</div>@if($item->productItem?->item_code)<div class="item-meta">{{ $item->productItem->item_code }}</div>@endif</td><td class="text-center">{{ rtrim(rtrim(number_format((float) $item->qty, 3), '0'), '.') }}</td><td class="text-right">{{ $money((float) $item->rate) }}</td><td class="text-right">{{ $money($lineDiscount) }}</td><td class="text-right">{{ $money($tax) }}</td><td class="text-right">{{ $money($lineTotal) }}</td></tr>
        @empty<tr><td colspan="7" class="text-center">No credit note items</td></tr>@endforelse
    </tbody></table>
    <section class="summary-layout">
        <div class="card"><div class="card-title">Company Bank Details</div>@if(filled($company?->additional_information))<div>{{ \Filament\Forms\Components\RichEditor\RichContentRenderer::make($company->additional_information) }}</div>@else<div class="muted">Bank details are available on request.</div>@endif</div>
        <div class="summary"><div class="summary-row"><span>Subtotal</span><strong>{{ $money($subtotal) }}</strong></div><div class="summary-row"><span>Discount</span><strong>{{ $money($discount) }}</strong></div><div class="summary-row"><span>Tax</span><strong>{{ $money($vatTotal) }}</strong></div><div class="summary-row"><span>Shipping Refund</span><strong>{{ $money((float) $invoiceTotals['shipping']) }}</strong></div><div class="summary-row total"><span>Grand Total</span><span>{{ $money($grandTotal) }}</span></div><div class="summary-row"><span>Paid</span><strong>{{ $money(0) }}</strong></div><div class="summary-row total"><span>Amount Due</span><span>{{ $money($grandTotal) }}</span></div></div>
    </section>
    <footer class="notes"><div class="card"><div class="card-title">Notes</div>@if(filled($invoice->notes) || $companyNote !== '')<div>{{ \Filament\Forms\Components\RichEditor\RichContentRenderer::make(filled($invoice->notes) ? $invoice->notes : $companyNote) }}</div>@else<div class="muted">This credit note is issued against the above invoice.</div>@endif</div><div class="footer">This is a system-generated credit note and no signature is required.</div></footer>
</main>
</body>
</html>
