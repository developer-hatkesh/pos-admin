<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sales Invoice {{ $invoice->invoice_no }}</title>
    <style>
        * { box-sizing: border-box; }
        :root { color: #172033; font-family: Arial, Helvetica, sans-serif; font-size: 12px; --accent: #1e40af; --border: #cbd5e1; --muted: #64748b; }
        body { margin: 0; background: #eef2f7; }
        .actions { display: flex; justify-content: flex-end; gap: 8px; width: min(210mm, calc(100% - 24px)); margin: 14px auto 0; }
        .actions a, .actions button { border: 0; border-radius: 5px; padding: 9px 16px; color: #fff; background: var(--accent); font-weight: 700; text-decoration: none; cursor: pointer; }
        .actions a { background: var(--muted); }
        .sheet { width: 210mm; min-height: 297mm; margin: 14px auto; padding: 13mm; background: #fff; box-shadow: 0 8px 30px rgba(0,0,0,.14); }
        .document-title { margin: 0 0 16px; color: var(--accent); font-size: 26px; text-align: right; text-transform: uppercase; }
        .two-column { display: grid; grid-template-columns: 1fr 1fr; gap: 28px; }
        .header { min-height: 105px; align-items: center; padding-bottom: 16px; border-bottom: 3px solid var(--accent); }
        .company-details { line-height: 1.5; }
        .company-name { margin-bottom: 4px; font-size: 17px; font-weight: 800; }
        .logo-wrap { display: flex; align-items: center; justify-content: center; min-height: 90px; }
        .logo { max-width: 190px; max-height: 85px; object-fit: contain; }
        .logo-placeholder { display: grid; place-items: center; width: 150px; height: 70px; border: 1px solid var(--border); color: var(--muted); font-weight: 800; text-align: center; }
        .section { padding: 16px 0; border-bottom: 1px solid var(--border); }
        .label { color: var(--muted); font-size: 10px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .detail-row { display: grid; grid-template-columns: 115px 1fr; gap: 8px; margin-top: 6px; }
        .detail-row strong, .detail-row span:last-child { text-align: right; }
        .address-card { min-height: 118px; padding: 13px; border: 1px solid var(--border); border-radius: 4px; line-height: 1.5; }
        .address-title { margin-bottom: 8px; color: var(--accent); font-size: 13px; font-weight: 800; text-transform: uppercase; }
        .address-name { font-weight: 800; }
        .items { width: 100%; margin-top: 18px; border-collapse: collapse; table-layout: fixed; }
        .items th { padding: 9px 5px; color: #fff; background: var(--accent); font-size: 10px; text-align: left; text-transform: uppercase; }
        .items td { padding: 9px 5px; border-bottom: 1px solid var(--border); vertical-align: top; }
        .items .num { width: 5%; text-align: center; } .items .description { width: 29%; }
        .items .qty { width: 8%; } .items .price { width: 14%; } .items .discount { width: 13%; } .items .tax { width: 13%; } .items .total { width: 18%; }
        .text-right { text-align: right !important; }
        .item-code { margin-top: 3px; color: var(--muted); font-size: 10px; }
        .bottom { align-items: start; margin-top: 22px; }
        .summary { border: 1px solid var(--border); }
        .summary-row { display: flex; justify-content: space-between; gap: 20px; padding: 7px 10px; border-bottom: 1px solid var(--border); }
        .summary-row:last-child { border: 0; }
        .summary-row.grand { color: var(--accent); font-size: 14px; font-weight: 800; }
        .bank { padding: 12px 14px; border-left: 4px solid var(--accent); background: #f8fafc; line-height: 1.55; }
        .bank-title { margin-bottom: 6px; font-size: 13px; font-weight: 800; }
        .notes { margin-top: 20px; padding: 12px; background: #f8fafc; color: var(--muted); }
        .footer { margin-top: 26px; padding-top: 10px; border-top: 1px solid var(--border); color: var(--muted); font-size: 10px; text-align: center; }
        @media (max-width: 820px) { .sheet { width: 100%; margin: 0; padding: 18px; } .actions { width: auto; } }
        @media print { body { background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; } .actions { display: none; } .sheet { width: auto; min-height: auto; margin: 0; padding: 0; box-shadow: none; } @page { size: A4 portrait; margin: 10mm; } }
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
    $subtotal = (float) $invoiceTotals['subtotal'];
    $discount = (float) $invoiceTotals['discount'];
@endphp
<div class="actions"><a href="{{ url()->previous() }}">Back</a><button type="button" onclick="window.print()">Print Invoice</button></div>

<main class="sheet">
    <h1 class="document-title">Sales Invoice</h1>

    <header class="header two-column">
        <div class="logo-wrap">
            @if(filled($logoUrl))<img class="logo" src="{{ $logoUrl }}" alt="{{ $company?->name }} logo">@else<div class="logo-placeholder">{{ $company?->name ?: 'Company Logo' }}</div>@endif
        </div>
        <div class="company-details">
            <div class="company-name">{{ $company?->legal_business_name ?: ($company?->name ?: 'Company') }}</div>
            @if($company?->address)<div>{{ $company->address }}</div>@endif
            @if(collect([$company?->city, $company?->postcode, $company?->country])->filter()->isNotEmpty())<div>{{ collect([$company?->city, $company?->postcode, $company?->country])->filter()->join(', ') }}</div>@endif
            @if($company?->email)<div>Email: {{ $company->email }}</div>@endif
            @if($company?->business_phone_number || $company?->phone)<div>Contact: {{ $company?->business_phone_number ?: $company?->phone }}</div>@endif
            @if($company?->vat_number)<div>VAT No: {{ $company->vat_number }}</div>@endif
        </div>
    </header>

    <section class="section two-column">
        <div>
            <div class="detail-row"><span class="label">Invoice Number</span><strong>{{ $invoice->invoice_no }}</strong></div>
            <div class="detail-row"><span class="label">Invoice Date</span><strong>{{ $invoice->invoice_date?->format('d M Y') }}</strong></div>
            @if($invoice->due_date)<div class="detail-row"><span class="label">Due Date</span><span>{{ $invoice->due_date->format('d M Y') }}</span></div>@endif
        </div>
        <div>
            <div class="detail-row"><span class="label">Reference Number</span><span>{{ $invoice->payment_note ?: '—' }}</span></div>
            <div class="detail-row"><span class="label">Invoice Currency</span><strong>{{ $currency }}</strong></div>
        </div>
    </section>

    <section class="section two-column">
        <div class="address-card"><div class="address-title">Invoice To</div><div class="address-name">{{ $customer?->company_name ?: ($customer?->name ?: 'Walk-in Customer') }}</div>@if($billingAddress)<div>{{ $billingAddress }}</div>@endif @if($customer?->phone)<div>{{ $customer->phone }}</div>@endif @if($customer?->email)<div>{{ $customer->email }}</div>@endif</div>
        <div class="address-card"><div class="address-title">Ship To</div><div class="address-name">{{ $customer?->company_name ?: ($customer?->name ?: 'Walk-in Customer') }}</div><div>{{ $shippingAddress ?: 'Same as invoice address' }}</div></div>
    </section>

    <table class="items">
        <thead><tr><th class="num">Sr.</th><th class="description">Description</th><th class="qty text-right">Qty</th><th class="price text-right">Unit Price</th><th class="discount text-right">Discount</th><th class="tax text-right">Tax</th><th class="total text-right">Line Total</th></tr></thead>
        <tbody>
        @forelse($invoice->items as $item)
            @php
                $gross = round((float) $item->qty * (float) $item->rate, 2);
                $lineDiscount = $subtotal > 0 ? round($discount * $gross / $subtotal, 2) : 0;
                $computedLine = $invoiceTotals['items'][$loop->index] ?? [];
                $lineTax = (float) ($computedLine['vat_amount'] ?? $item->vat_amount);
                $lineTotal = (float) ($computedLine['line_total'] ?? $item->line_total) - $lineDiscount;
            @endphp
            <tr><td class="num">{{ $loop->iteration }}</td><td><strong>{{ $item->description ?: ($item->productItem?->name ?: 'Item') }}</strong>@if($item->productItem?->item_code)<div class="item-code">Code: {{ $item->productItem->item_code }}</div>@endif</td><td class="text-right">{{ rtrim(rtrim(number_format((float) $item->qty, 3), '0'), '.') }}</td><td class="text-right">{{ app_money((float) $item->rate) }}</td><td class="text-right">{{ app_money($lineDiscount) }}</td><td class="text-right">{{ app_money($lineTax) }}</td><td class="text-right"><strong>{{ app_money($lineTotal) }}</strong></td></tr>
        @empty
            <tr><td class="num">1</td><td>No invoice items</td><td colspan="5"></td></tr>
        @endforelse
        </tbody>
    </table>

    <section class="bottom two-column">
        <div class="bank"><div class="bank-title">Company Bank Details</div>@if($bank)<div>{{ $bank->bank_name }}</div><div>Account Name: {{ $bank->account_name }}</div><div>Account No: {{ $bank->account_number }}</div>@if($bank->sort_code)<div>Sort Code: {{ $bank->sort_code }}</div>@endif @else<div>Bank details are available on request.</div>@endif</div>
        <div class="summary">
            <div class="summary-row"><span>Subtotal</span><span>{{ app_money($subtotal) }}</span></div>
            <div class="summary-row"><span>Discount</span><span>{{ app_money($discount) }}</span></div>
            <div class="summary-row"><span>Tax</span><span>{{ app_money((float) $invoiceTotals['vat_total']) }}</span></div>
            <div class="summary-row grand"><span>Grand Total</span><span>{{ app_money((float) $invoiceTotals['total']) }}</span></div>
            <div class="summary-row"><span>Paid</span><span>{{ app_money((float) $paidAmount) }}</span></div>
            <div class="summary-row grand"><span>Amount Due</span><span>{{ app_money((float) $dueAmount) }}</span></div>
        </div>
    </section>

    @if(filled($invoice->notes))<section class="notes"><strong>Notes</strong><br>{{ $invoice->notes }}</section>@endif
    <footer class="footer">This is a system-generated invoice and no signature is required.</footer>
</main>
</body>
</html>
