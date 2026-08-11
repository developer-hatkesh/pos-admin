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
        .label-text { font-style: var(--label-font-style); font-weight: var(--label-font-weight); } .content-text { font-style: var(--content-font-style); font-weight: var(--content-font-weight); }
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
    $printSubtotal = (float) ($totals['subtotal'] ?? $document->subtotal);
    $printDiscount = (float) ($totals['discount'] ?? $document->discount ?? 0);
    $printVatTotal = (float) ($totals['vat_total'] ?? $document->vat_total);
    $printShipping = (float) ($totals['shipping'] ?? $document->shipping ?? 0);
    $printTotal = (float) ($totals['total'] ?? $document->total);
    $settings = $receiptSettings ?? [];
    $setting = fn (string $key, bool $default = true): bool => array_key_exists($key, $settings) ? (bool) $settings[$key] : $default;
    $showNote = $setting('receipt_show_note');
    $showPhone = $setting('receipt_show_phone');
    $showCustomer = $setting('receipt_show_customer');
    $showAddress = $setting('receipt_show_address');
    $showEmail = $setting('receipt_show_email');
    $showDiscount = $setting('receipt_show_discount_shipping');
    $showProductCode = $setting('receipt_show_product_code');
    $showTax = $setting('receipt_show_tax');
    $fontCss = fn (string $style): array => match ($style) { 'bold' => ['normal', '700'], 'italic' => ['italic', '400'], default => ['normal', '400'] };
    [$labelFontStyle, $labelFontWeight] = $fontCss((string) ($settings['receipt_labels_font_style'] ?? 'bold'));
    [$contentFontStyle, $contentFontWeight] = $fontCss((string) ($settings['receipt_other_font_style'] ?? 'normal'));
    $companyNote = trim((string) ($company?->notes ?? ''));
    $documentCurrency = $partyRelation === 'supplier' ? ($document->currency_id ?: $party?->currency_id) : null;
    $money = fn (float|int|string|null $amount): string => $documentCurrency
        ? \App\Support\CurrencyFormatter::formatForCurrency($amount, $documentCurrency)
        : app_money($amount);
@endphp
<div class="actions"><a href="{{ url()->previous() }}">Back</a><button type="button" onclick="window.print()">Print</button></div>
<main class="page content-text" style="--label-font-style: {{ $labelFontStyle }}; --label-font-weight: {{ $labelFontWeight }}; --content-font-style: {{ $contentFontStyle }}; --content-font-weight: {{ $contentFontWeight }};">
    <header class="header">
        <div><h1>{{ $title }}</h1><p class="muted">{{ $document->{$numberColumn} }}</p></div>
        <div class="box right"><h2 class="label-text">{{ $company?->name ?: 'Company' }}</h2>@if($showAddress)<p>{{ $company?->address }}</p><p>{{ collect([$company?->city, $company?->postcode])->filter()->join(', ') }}</p>@endif @if($showPhone)<p>{{ $company?->phone }}</p>@endif @if($showEmail)<p>{{ $company?->email }}</p>@endif @if($showTax && $company?->vat_number)<p><span class="label-text">VAT:</span> {{ $company->vat_number }}</p>@endif</div>
    </header>
    <section class="meta">
        @if($showCustomer)<div class="box"><h2 class="label-text">{{ $partyRelation === 'supplier' ? 'Supplier' : 'Customer' }}</h2><p><span class="label-text">{{ $party?->company_name ?: ($party?->name ?: 'N/A') }}</span></p>@if($showAddress)<p>{{ $address }}</p><p>{{ collect([$party?->city, $party?->postcode])->filter()->join(', ') }}</p>@endif @if($showPhone)<p>{{ $party?->phone ?: $party?->mobile_no }}</p>@endif @if($showEmail)<p>{{ $party?->email }}</p>@endif</div>@endif
        <div class="box right">@if($document instanceof \App\Models\PurchaseInvoice)<p><span class="label-text">Voucher Number:</span> {{ $document->voucherNumber() }}</p><p><span class="label-text">Supplier Invoice Number:</span> {{ $document->supplierInvoiceNumber() ?: '—' }}</p>@endif<p><span class="label-text">Date:</span> {{ $document->{$dateColumn}?->format('d M Y') }}</p>@if($dueDateColumn && $document->{$dueDateColumn})<p><span class="label-text">{{ $title === 'Estimate' ? 'Expiry' : 'Due' }}:</span> {{ $document->{$dueDateColumn}->format('d M Y') }}</p>@endif<p><span class="label-text">Status:</span> {{ ucfirst($status) }}</p></div>
    </section>
    <table><thead><tr><th class="label-text">Description</th><th class="text-right label-text">Qty</th><th class="text-right label-text">Rate</th>@if($showTax)<th class="text-right label-text">VAT</th>@endif<th class="text-right label-text">Line Total</th></tr></thead><tbody>
    @foreach($document->items as $item)<tr><td><span class="label-text">{{ $item->description ?: $item->productItem?->name }}</span>@if($showProductCode && $item->productItem?->item_code)<p class="muted">Code: {{ $item->productItem->item_code }}</p>@endif</td><td class="text-right">{{ rtrim(rtrim(number_format((float) $item->qty, 3), '0'), '.') }}</td><td class="text-right">{{ $money((float) $item->rate) }}</td>@if($showTax)<td class="text-right">{{ $money((float) $item->vat_amount) }}</td>@endif<td class="text-right">{{ $money((float) $item->line_total) }}</td></tr>@endforeach
    </tbody></table>
    <section class="totals"><div class="summary-row"><span class="label-text">Subtotal</span><span>{{ $money($printSubtotal) }}</span></div>@if($showDiscount && isset($document->discount))<div class="summary-row"><span class="label-text">Discount</span><span>{{ $money($printDiscount) }}</span></div>@endif @if($showTax)<div class="summary-row"><span class="label-text">VAT</span><span>{{ $money($printVatTotal) }}</span></div>@endif<div class="summary-row"><span class="label-text">Shipping</span><span>{{ $money($printShipping) }}</span></div><div class="summary-row total"><span class="label-text">Total</span><span class="label-text">{{ $money($printTotal) }}</span></div>@if($paid !== null)<div class="summary-row"><span class="label-text">Paid</span><span>{{ $money($paid) }}</span></div><div class="summary-row total"><span class="label-text">Amount Due</span><span class="label-text">{{ $money((float) $due) }}</span></div>@endif</section>
    @if($showNote && (filled($document->notes) || $companyNote !== ''))<section class="notes"><span class="label-text">Notes</span><div>{{ \Filament\Forms\Components\RichEditor\RichContentRenderer::make(filled($document->notes) ? $document->notes : $companyNote) }}</div></section>@endif
</main>
</body>
</html>
