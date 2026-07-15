<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Commercial Invoice - {{ $invoice->invoice_no }}</title>
    <style>
        * { box-sizing: border-box; }
        :root { --primary: #1e3a8a; --ink: #131b2e; --muted: #5f6470; --border: #c5c5d3; --soft: #f2f3ff; --panel: #eaedff; }
        body { margin: 0; padding: 0 0 28px; color: var(--ink); background: var(--soft); font: 12px/1.45 Arial, Helvetica, sans-serif; }
        .toolbar { position: sticky; top: 0; z-index: 10; display: flex; align-items: center; justify-content: space-between; padding: 10px 18px; color: var(--primary); background: #fff; border-bottom: 1px solid var(--border); }
        .toolbar-title { color: var(--primary); font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
        .toolbar-actions { display: flex; gap: 8px; }
        .toolbar a, .toolbar button { padding: 8px 14px; color: #fff; background: var(--primary); border: 0; border-radius: 3px; font-weight: 700; text-decoration: none; cursor: pointer; }
        .toolbar a { color: var(--primary); background: var(--soft); }
        .sheet { width: min(210mm, calc(100% - 24px)); min-height: 280mm; margin: 18px auto 0; background: #fff; border: 4px solid var(--border); box-shadow: 0 2px 8px rgba(19, 27, 46, .08); }
        .brand-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 24px; padding: 16px; border-bottom: 1px solid var(--border); }
        .logo-box { display: flex; align-items: center; justify-content: center; width: 128px; height: 128px; padding: 8px; background: #fff; }
        .logo { max-width: 100%; max-height: 100%; object-fit: contain; }
        .company-details { max-width: 330px; color: var(--muted); text-align: right; }
        .company-name { margin-bottom: 6px; color: var(--primary); font-weight: 800; }
        .meta-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; padding: 14px 16px; border-bottom: 1px solid var(--border); }
        .label { display: block; margin-bottom: 3px; color: var(--primary); font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
        .meta-value { font-weight: 700; }
        .meta-value.primary { color: var(--primary); }
        .addresses { display: grid; grid-template-columns: 1fr 1fr; border-bottom: 1px solid var(--border); }
        .address { min-height: 145px; padding: 16px; }
        .address + .address { border-left: 1px solid var(--border); }
        .section-title { margin: 0 0 10px; padding-bottom: 7px; color: var(--primary); border-bottom: 1px solid var(--panel); font-weight: 800; letter-spacing: .03em; text-transform: uppercase; }
        .address-name { margin-bottom: 2px; font-weight: 800; }
        .address-detail { color: var(--muted); }
        .items { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .items th { padding: 10px 9px; color: #fff; background: var(--primary); font-weight: 800; letter-spacing: .04em; text-align: left; text-transform: uppercase; }
        .items td { padding: 11px 9px; border-bottom: 1px solid var(--border); vertical-align: top; }
        .items .num { width: 6%; } .items .description { width: 29%; } .items .qty { width: 8%; } .items .money { width: 14%; } .items .total { width: 15%; }
        .text-right { text-align: right !important; }
        .item-code { margin-top: 2px; color: var(--muted); }
        .bottom { display: grid; grid-template-columns: 1fr 1fr; border-bottom: 1px solid var(--border); }
        .bank { padding: 16px; background: var(--soft); border-right: 1px solid var(--border); }
        .bank-name { margin-bottom: 4px; font-weight: 800; }
        .bank-row span:first-child { color: var(--muted); }
        .summary { padding: 10px 16px; }
        .summary-row { display: flex; justify-content: space-between; gap: 20px; padding: 5px 0; }
        .summary-row.divider { padding-bottom: 8px; border-bottom: 1px solid var(--border); }
        .summary-row.grand { padding: 9px 0; color: var(--primary); border-bottom: 1px solid var(--border); font-weight: 800; text-transform: uppercase; }
        .summary-row.due { padding-top: 9px; color: var(--primary); font-weight: 800; text-transform: uppercase; }
        .notes { padding: 14px 16px; background: var(--panel); }
        .footer { margin: 26px 16px 14px; padding-top: 10px; color: var(--muted); border-top: 1px solid rgba(197, 197, 211, .5); text-align: center; }
        @media (max-width: 700px) { .meta-grid { grid-template-columns: 1fr 1fr; } .addresses, .bottom { grid-template-columns: 1fr; } .address + .address, .bank { border-left: 0; border-right: 0; border-top: 1px solid var(--border); } .sheet { width: 100%; margin: 0; border-width: 1px; } }
        @media print { body { background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; } .toolbar { display: none; } .sheet { width: auto; min-height: auto; margin: 0; border: 2px solid var(--border); box-shadow: none; } @page { size: A4 portrait; margin: 8mm; } }
    </style>
</head>
<body>
@php
    $company = $invoice->company;
    $customer = $invoice->customer;
    $bank = $company?->bankAccounts?->first();
    $currency = $customer?->currency_id ?: (app_currency_settings()['currency_default'] ?? 'GBP');
    $billingAddress = $customer?->billing_address ?: collect([$customer?->address_line1, $customer?->address_line2, $customer?->city, $customer?->postcode, $customer?->country])->filter()->join(', ');
    $shippingAddress = $customer?->delivery_address ?: $billingAddress;
    $customerVatNumber = $customer?->tax_number ?: $customer?->vat_number;
    $subtotal = (float) $invoiceTotals['subtotal'];
    $discount = (float) $invoiceTotals['discount'];
@endphp

<header class="toolbar">
    <div class="toolbar-title">Commercial Invoice</div>
    <div class="toolbar-actions"><a href="{{ url()->previous() }}">Back</a><button type="button" onclick="window.print()">Print Invoice</button></div>
</header>

<main class="sheet">
    <section class="brand-header">
        <div class="logo-box">
            @if(filled($logoUrl))
                <img class="logo" src="{{ $logoUrl }}" alt="{{ $company?->name }} logo">
            @else
                <img class="logo" src="{{ asset('images/logo.png') }}" alt="Default company logo">
            @endif
        </div>
        <div class="company-details">
            <div class="company-name">{{ $company?->legal_business_name ?: ($company?->name ?: 'Company') }}</div>
            @if($company?->address)<div>{{ $company->address }}</div>@endif
            @if(collect([$company?->city, $company?->postcode, $company?->country])->filter()->isNotEmpty())<div>{{ collect([$company?->city, $company?->postcode, $company?->country])->filter()->join(', ') }}</div>@endif
            @if($company?->email)<div>Email: {{ $company->email }}</div>@endif
            @if($company?->business_phone_number || $company?->phone)<div>Contact: {{ $company?->business_phone_number ?: $company?->phone }}</div>@endif
            @if($company?->vat_number)<div><strong>VAT No: {{ $company->vat_number }}</strong></div>@endif
        </div>
    </section>

    <section class="meta-grid">
        <div><span class="label">Invoice Number</span><span class="meta-value primary">{{ $invoice->invoice_no }}</span></div>
        <div><span class="label">Invoice Date</span><span class="meta-value">{{ $invoice->invoice_date?->format('d M Y') }}</span></div>
        <div><span class="label">Ref Number</span><span class="meta-value">{{ $invoice->payment_note ?: '—' }}</span></div>
        <div><span class="label">Currency</span><span class="meta-value">{{ strtoupper($currency) }}</span></div>
        @if($invoice->due_date)<div><span class="label">Due Date</span><span class="meta-value">{{ $invoice->due_date->format('d M Y') }}</span></div>@endif
    </section>

    <section class="addresses">
        <div class="address">
            <h2 class="section-title">Invoice To</h2>
            <div class="address-name">{{ $customer?->company_name ?: ($customer?->name ?: 'Walk-in Customer') }}</div>
            @if($customer?->contact_person)<div class="address-detail">Attn: {{ $customer->contact_person }}</div>@endif
            @if($billingAddress)<div class="address-detail">{{ $billingAddress }}</div>@endif
            @if($customer?->phone)<div class="address-detail">{{ $customer->phone }}</div>@endif
            @if($customer?->email)<div class="address-detail">{{ $customer->email }}</div>@endif
            @if($customerVatNumber)<div class="address-detail"><strong>VAT No: {{ $customerVatNumber }}</strong></div>@endif
        </div>
        <div class="address">
            <h2 class="section-title">Ship To</h2>
            <div class="address-name">{{ $customer?->company_name ?: ($customer?->name ?: 'Walk-in Customer') }}</div>
            <div class="address-detail">{{ $shippingAddress ?: 'Same as invoice address' }}</div>
        </div>
    </section>

    <table class="items">
        <thead><tr><th class="num">Sr.</th><th class="description">Description</th><th class="qty text-right">Qty</th><th class="money text-right">Unit Price</th><th class="money text-right">Discount</th><th class="money text-right">Tax</th><th class="total text-right">Line Total</th></tr></thead>
        <tbody>
        @forelse($invoice->items as $item)
            @php
                $gross = round((float) $item->qty * (float) $item->rate, 2);
                $lineDiscount = $subtotal > 0 ? round($discount * $gross / $subtotal, 2) : 0;
                $computedLine = $invoiceTotals['items'][$loop->index] ?? [];
                $lineTax = (float) ($computedLine['vat_amount'] ?? $item->vat_amount);
                $lineTotal = (float) ($computedLine['line_total'] ?? $item->line_total) - $lineDiscount;
            @endphp
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td><strong>{{ $item->description ?: ($item->productItem?->name ?: 'Item') }}</strong>@if($item->productItem?->item_code)<div class="item-code">Code: {{ $item->productItem->item_code }}</div>@endif</td>
                <td class="text-right">{{ rtrim(rtrim(number_format((float) $item->qty, 3), '0'), '.') }}</td>
                <td class="text-right">{{ app_money((float) $item->rate) }}</td>
                <td class="text-right">{{ app_money($lineDiscount) }}</td>
                <td class="text-right">{{ app_money($lineTax) }}</td>
                <td class="text-right"><strong>{{ app_money($lineTotal) }}</strong></td>
            </tr>
        @empty
            <tr><td>1</td><td>No invoice items</td><td colspan="5"></td></tr>
        @endforelse
        </tbody>
    </table>

    <section class="bottom">
        <div class="bank">
            <h2 class="section-title">Company Bank Details</h2>
            @if($bank)
                <div class="bank-name">{{ $bank->bank_name }}</div>
                <div class="bank-row"><span>Account Name:</span> {{ $bank->account_name }}</div>
                <div class="bank-row"><span>Account No:</span> {{ $bank->account_number }}</div>
                @if($bank->sort_code)<div class="bank-row"><span>Sort Code:</span> {{ $bank->sort_code }}</div>@endif
            @else
                <div>Bank details are available on request.</div>
            @endif
        </div>
        <div class="summary">
            <div class="summary-row"><span>Subtotal</span><span>{{ app_money($subtotal) }}</span></div>
            <div class="summary-row"><span>Discount</span><span>{{ app_money($discount) }}</span></div>
            <div class="summary-row divider"><span>Tax</span><span>{{ app_money((float) $invoiceTotals['vat_total']) }}</span></div>
            <div class="summary-row grand"><span>Grand Total</span><span>{{ app_money((float) $invoiceTotals['total']) }}</span></div>
            <div class="summary-row"><span>Paid</span><span>{{ app_money((float) $paidAmount) }}</span></div>
            <div class="summary-row due"><span>Amount Due</span><span>{{ app_money((float) $dueAmount) }}</span></div>
        </div>
    </section>

    @if(filled($invoice->notes))<section class="notes"><span class="label">Notes</span>{{ $invoice->notes }}</section>@endif
    <footer class="footer">This is a system-generated invoice and no signature is required.</footer>
</main>
</body>
</html>
