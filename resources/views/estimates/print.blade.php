<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Estimate - {{ $estimate->estimate_no }}</title>
    <style>
        * { box-sizing: border-box; }
        :root { --primary: #1F4E79; --border: #D9DEE5; --soft: #F8FAFC; --text: #2D3748; --muted: #6B7280; }
        html, body { margin: 0; color: var(--text); background: var(--soft); font: 10pt/1.4 Arial, Helvetica, sans-serif; }
        .toolbar { position: sticky; top: 0; z-index: 10; display: flex; align-items: center; justify-content: space-between; padding: 10px 18px; background: #fff; border-bottom: 1px solid var(--border); }
        .toolbar-title { color: var(--primary); font-weight: 600; text-transform: uppercase; }
        .toolbar-actions { display: flex; gap: 8px; }
        .toolbar a, .toolbar button { padding: 8px 14px; color: #fff; background: var(--primary); border: 0; border-radius: 4px; font-weight: 600; text-decoration: none; cursor: pointer; }
        .toolbar a { color: var(--primary); background: var(--soft); }
        .sheet { position: relative; width: min(210mm, calc(100% - 24px)); min-height: 297mm; margin: 18px auto; padding: 11mm 11mm 22mm; background: #fff; box-shadow: 0 2px 10px rgba(45,55,72,.12); }
        .sheet::before { position: absolute; inset: 5mm; border: 1px solid var(--primary); content: ""; pointer-events: none; }
        .invoice-title { margin: 1mm 0 1mm; color: var(--primary); font-size: 16px; line-height: 1.1; font-weight: 700; text-align: center; }
        .brand-header { display: grid; grid-template-columns: 35% 65%; align-items: center; margin-bottom: 6mm; }
        .logo-box { display: flex; align-items: center; justify-content: flex-start; height: 112px; }
        .logo { max-width: 100%; max-height: 108px; object-fit: contain; }
        .company-details { color: var(--text); text-align: right; }
        .company-name { margin-bottom: 3px; color: var(--text); font-weight: 600; }
        .detail-label, .label, .section-title { color: var(--primary); font-weight: 600; }
        .info-table, .items { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .info-table { margin-bottom: 5mm; border: 1px solid var(--border); }
        .info-table th, .info-table td { width: 25%; padding: 7px 8px; background: #fff; border-right: 1px solid var(--border); text-align: left; }
        .info-table th:last-child, .info-table td:last-child { border-right: 0; }
        .info-table th { padding-bottom: 2px; color: var(--primary); font-weight: 600; }
        .info-table td { padding-top: 2px; color: var(--text); }
        .addresses { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 5mm; }
        .address-card { min-height: 126px; padding: 8px; border: 1px solid var(--border); border-radius: 4px; }
        .section-title { margin: 0 0 7px; font-size: 10pt; }
        .address-name { margin-bottom: 3px; font-weight: 600; }
        .address-detail { color: var(--muted); }
        .items { margin-bottom: 5mm; }
        .items th { padding: 8px 6px; color: #fff; background: var(--primary); border: 1px solid var(--primary); font-weight: 600; text-align: left; }
        .items td { height: 42px; padding: 7px 6px; border: 1px solid #E5E7EB; vertical-align: top; }
        .items tbody tr:nth-child(even) td { background: #FAFAFA; }
        .items .num { width: 6%; } .items .description { width: 31%; } .items .qty { width: 8%; } .items .money { width: 13%; } .items .total { width: 16%; }
        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }
        .item-meta { margin-top: 2px; color: var(--muted); font-size: 9pt; }
        .bottom { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 5mm; }
        .bank, .summary { padding: 8px; }
        .bank { border: 1px solid var(--border); border-radius: 4px; }
        .bank-row { margin: 2px 0; }
        .bank-row .detail-label { display: inline-block; min-width: 96px; }
        .summary-row { display: flex; justify-content: space-between; gap: 20px; padding: 4px 0; }
        .summary-row.divider { border-bottom: 1px solid var(--border); }
        .summary-row.grand, .summary-row.due { padding: 7px 0; color: var(--primary); border-bottom: 1px solid var(--border); font-weight: 600; }
        .notes { padding: 8px; background: var(--soft); border: 1px solid var(--border); border-radius: 4px; }
        .notes .section-title { margin-bottom: 4px; }
        .footer { position: absolute; right: 11mm; bottom: 6mm; left: 11mm; padding-top: 7px; color: var(--muted); border-top: 1px solid var(--border); font-size: 9pt; text-align: center; }
        @media (max-width: 700px) { .sheet { width: 100%; margin: 0; padding: 18px 12px 70px; } .brand-header { grid-template-columns: 35% 65%; } .addresses, .bottom { grid-template-columns: 1fr; } .footer { right: 12px; left: 12px; } }
        @media print { @page { size: A4 portrait; margin: 0; } html, body { background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; } .toolbar { display: none; } .sheet { width: 210mm; min-height: 297mm; margin: 0; box-shadow: none; } }
    </style>
</head>
<body>
@php
    $company = $estimate->company;
    $customer = $estimate->customer;
    $bank = $company?->bankAccounts?->first();
    $billingAddress = $customer?->billing_address ?: collect([$customer?->address_line1, $customer?->address_line2, $customer?->city, $customer?->postcode, $customer?->country])->filter()->join(', ');
    $shippingAddress = $customer?->delivery_address ?: $billingAddress;
    $customerVat = $customer?->tax_number ?: $customer?->vat_number;
    $customerPhone = $customer?->phone ?: ($customer?->telephone_no ?: $customer?->mobile_no);
    $subtotal = (float) $estimateTotals['subtotal'];
    $discount = (float) $estimateTotals['discount'];
    $setting = fn (string $key, bool $default = true): bool => array_key_exists($key, $receiptSettings ?? []) ? (bool) $receiptSettings[$key] : $default;
    $showNote = $setting('receipt_show_note');
    $showPhone = $setting('receipt_show_phone');
    $showCustomer = $setting('receipt_show_customer');
    $showAddress = $setting('receipt_show_address');
    $showEmail = $setting('receipt_show_email');
    $showDiscount = $setting('receipt_show_discount_shipping');
    $showProductCode = $setting('receipt_show_product_code');
    $showTax = $setting('receipt_show_tax');
    $fontCss = fn (string $style): string => match ($style) { 'bold' => 'font-weight: 700;', 'italic' => 'font-style: italic;', default => 'font-weight: 400;' };
    $labelFontCss = $fontCss((string) ($receiptSettings['receipt_labels_font_style'] ?? 'bold'));
    $otherFontCss = $fontCss((string) ($receiptSettings['receipt_other_font_style'] ?? 'normal'));
    $receiptNote = trim((string) ($receiptSettings['receipt_note'] ?? ''));
@endphp
<header class="toolbar"><div class="toolbar-title">Estimate</div><div class="toolbar-actions"><a href="{{ url()->previous() }}">Back</a><button type="button" onclick="window.print()">Print Estimate</button></div></header>
<main class="sheet" style="{{ $otherFontCss }}">
    <h1 class="invoice-title">ESTIMATE</h1>
    <section class="brand-header">
        <div class="logo-box"><img class="logo" src="{{ filled($logoUrl) ? $logoUrl : asset('images/logo.png') }}" alt="{{ $company?->name ?: 'Company' }} logo"></div>
        <div class="company-details">
            <div class="company-name">{{ $company?->legal_business_name ?: ($company?->name ?: 'Company') }}</div>
            @if($showAddress && $company?->address)<div>{{ $company->address }}</div>@endif
            @if($showAddress && ($company?->city || $company?->postcode))<div>{{ collect([$company?->city, $company?->postcode])->filter()->join(', ') }}</div>@endif
            @if($showAddress && $company?->country)<div>{{ $company->country }}</div>@endif
            @if($showTax && $company?->vat_number)<div><span class="detail-label">VAT No:</span> {{ $company->vat_number }}</div>@endif
            @if($company?->company_house_number)<div><span class="detail-label">Company No:</span> {{ $company->company_house_number }}</div>@endif
            @if($showEmail && $company?->email)<div><span class="detail-label">Email:</span> {{ $company->email }}</div>@endif
            @if($company?->website)<div><span class="detail-label">Website:</span> {{ $company->website }}</div>@endif
            @if($showPhone && ($company?->business_phone_number || $company?->phone))<div><span class="detail-label">Phone:</span> {{ $company?->business_phone_number ?: $company?->phone }}</div>@endif
        </div>
    </section>
    <table class="info-table"><thead><tr><th>Estimate No</th><th>Estimate Date</th><th>Expiry Date</th><th>Reference</th></tr></thead><tbody><tr><td>{{ $estimate->estimate_no }}</td><td>{{ $estimate->estimate_date?->format('d M Y') ?: '—' }}</td><td>{{ $estimate->expiry_date?->format('d M Y') ?: '—' }}</td><td>{{ $estimate->reference ?: '—' }}</td></tr></tbody></table>
    @if($showCustomer)<section class="addresses">
        <div class="address-card"><h2 class="section-title">Estimate To</h2><div class="address-name">{{ $customer?->company_name ?: ($customer?->name ?: 'Walk-in Customer') }}</div>@if($showAddress && $billingAddress)<div class="address-detail">{{ $billingAddress }}</div>@endif @if($showEmail && $customer?->email)<div class="address-detail">{{ $customer->email }}</div>@endif @if($showTax && $customerVat)<div class="address-detail"><span class="detail-label">VAT No:</span> {{ $customerVat }}</div>@endif @if($showPhone && $customerPhone)<div class="address-detail">{{ $customerPhone }}</div>@endif</div>
        <div class="address-card"><h2 class="section-title">Ship To</h2><div class="address-name">{{ $customer?->company_name ?: ($customer?->name ?: 'Walk-in Customer') }}</div>@if($showAddress)<div class="address-detail">{{ $shippingAddress ?: 'Same as estimate address' }}</div>@endif @if($showEmail && $customer?->email)<div class="address-detail">{{ $customer->email }}</div>@endif @if($showPhone && $customerPhone)<div class="address-detail">{{ $customerPhone }}</div>@endif</div>
    </section>@endif
    <table class="items"><thead style="{{ $labelFontCss }}"><tr><th class="num">Sr</th><th class="description">Description</th><th class="qty text-center">Qty</th><th class="money text-center">Unit Price</th>@if($showDiscount)<th class="money text-center">Discount</th>@endif @if($showTax)<th class="money text-center">Tax</th>@endif<th class="total text-center">Line Total</th></tr></thead><tbody>
    @forelse($estimate->items as $item)
        @php
            $gross = round((float) $item->qty * (float) $item->rate, 2);
            $lineDiscount = $subtotal > 0 ? round($discount * $gross / $subtotal, 2) : 0;
            $computedLine = $estimateTotals['items'][$loop->index] ?? [];
            $lineTax = (float) ($computedLine['vat_amount'] ?? $item->vat_amount);
            $lineTotal = (float) ($computedLine['line_total'] ?? $item->line_total) - $lineDiscount;
            $product = $item->productItem;
        @endphp
        <tr><td>{{ $loop->iteration }}</td><td><strong>{{ $item->description ?: ($product?->name ?: 'Item') }}</strong>@if($showProductCode && $product?->item_code)<div class="item-meta">Code: {{ $product->item_code }}</div>@endif @if($product?->variation?->name)<div class="item-meta">{{ $product->variation->name }}: {{ $product?->variationType?->name }}</div>@endif</td><td class="text-center">{{ rtrim(rtrim(number_format((float) $item->qty, 3), '0'), '.') }}</td><td class="text-center">{{ app_money((float) $item->rate) }}</td>@if($showDiscount)<td class="text-center">{{ app_money($lineDiscount) }}</td>@endif @if($showTax)<td class="text-center">{{ app_money($lineTax) }}</td>@endif<td class="text-center"><strong>{{ app_money($lineTotal) }}</strong></td></tr>
    @empty <tr><td colspan="{{ 5 + (int) $showDiscount + (int) $showTax }}">No estimate items</td></tr> @endforelse
    </tbody></table>
    <section class="bottom">
        <div class="bank"><h2 class="section-title">Company Bank Details</h2>@if($bank)<div class="bank-row"><span class="detail-label">Bank Name:</span> {{ $bank->bank_name }}</div><div class="bank-row"><span class="detail-label">Account Name:</span> {{ $bank->account_name }}</div><div class="bank-row"><span class="detail-label">Account Number:</span> {{ $bank->account_number }}</div>@if($bank->sort_code)<div class="bank-row"><span class="detail-label">Sort Code:</span> {{ $bank->sort_code }}</div>@endif @if($bank->iban)<div class="bank-row"><span class="detail-label">IBAN:</span> {{ $bank->iban }}</div>@endif @if($bank->swift)<div class="bank-row"><span class="detail-label">SWIFT:</span> {{ $bank->swift }}</div>@endif @else <div>Bank details are available on request.</div> @endif</div>
        <div class="summary"><div class="summary-row"><span>Subtotal</span><span>{{ app_money($subtotal) }}</span></div>@if($showDiscount)<div class="summary-row"><span>Discount</span><span>{{ app_money($discount) }}</span></div>@endif @if($showTax)<div class="summary-row divider"><span>Tax</span><span>{{ app_money((float) $estimateTotals['vat_total']) }}</span></div>@endif<div class="summary-row grand"><span>Grand Total</span><span>{{ app_money((float) $estimateTotals['total']) }}</span></div></div>
    </section>
    @if($showNote)<section class="notes"><h2 class="section-title">Notes</h2>@if(filled($estimate->notes))<div>{{ \Filament\Forms\Components\RichEditor\RichContentRenderer::make($estimate->notes) }}</div>@endif @if($receiptNote !== '')<div>{{ \Filament\Forms\Components\RichEditor\RichContentRenderer::make($receiptNote) }}</div>@endif</section>@endif
    <footer class="footer">This is a system-generated estimate and no signature is required.</footer>
</main>
</body>
</html>
