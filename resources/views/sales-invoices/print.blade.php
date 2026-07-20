<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $isCredit = ($documentType ?? 'invoice') === 'credit-note';
        $documentTitle = $isCredit ? 'Credit Note' : 'Sales Invoice';
        $documentNumber = $isCredit ? $invoice->return_no : $invoice->invoice_no;
    @endphp
    <title>{{ $documentTitle }} - {{ $documentNumber }}</title>
    <style>
        * { box-sizing: border-box; }
        :root { --primary: #1f4e79; --primary-dark: #173b5d; --border: #aeb9c5; --soft: #e8eef7; --text: #17202a; --muted: #5f6b76; }
        html, body { margin: 0; color: var(--text); background: #f3f5f7; font: 10pt/1.35 Arial, Helvetica, sans-serif; }
        .toolbar { position: sticky; top: 0; z-index: 10; display: flex; justify-content: space-between; padding: 10px 18px; background: #fff; border-bottom: 1px solid var(--border); }
        .toolbar-title { color: var(--primary); font-weight: 700; text-transform: uppercase; }
        .toolbar-actions { display: flex; gap: 8px; }
        .toolbar a, .toolbar button { padding: 8px 14px; border: 0; border-radius: 4px; color: #fff; background: var(--primary); font-weight: 700; text-decoration: none; cursor: pointer; }
        .toolbar a { color: var(--primary); background: var(--soft); }
        .sheet { width: min(210mm, calc(100% - 24px)); min-height: 297mm; margin: 18px auto; padding: 10mm 9mm 12mm; background: #fff; box-shadow: 0 2px 12px rgba(23,32,42,.14); }
        .top { display: grid; grid-template-columns: 55% 45%; gap: 12mm; margin-bottom: 7mm; }
        h1 { margin: 0 0 2mm; color: var(--primary); font-size: 27pt; line-height: 1; letter-spacing: .4px; text-transform: uppercase; }
        .company-name { margin-bottom: 2px; font-size: 12pt; font-weight: 700; }
        .company-logo { max-width: 52mm; max-height: 18mm; margin: 0 0 3mm; object-fit: contain; object-position: left center; }
        .meta { width: 100%; margin-top: 10mm; border-collapse: collapse; }
        .meta th, .meta td { padding: 2px 3px; vertical-align: top; text-align: left; }
        .meta th { width: 52%; font-weight: 600; }
        .meta .colon { width: 5%; }
        .meta .number { color: var(--primary); font-weight: 800; }
        .panels { display: grid; grid-template-columns: 54% 42%; justify-content: space-between; gap: 4%; margin-bottom: 5mm; }
        .panel { min-height: 37mm; border: 1px solid var(--text); }
        .panel-title { display: inline-block; min-width: 36%; margin: -1px 0 5px -1px; padding: 5px 8px; color: #fff; background: var(--primary); font-weight: 700; }
        .panel-body { padding: 0 7px 7px; white-space: pre-line; }
        .customer-name { font-weight: 700; }
        .items { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .items th { padding: 6px 4px; color: #fff; background: var(--primary); border: 1px solid #fff; font-size: 9pt; text-align: center; }
        .items td { height: 27mm; padding: 7px 5px; border: 1px solid var(--border); vertical-align: top; }
        .items .code { width: 11%; } .items .description { width: 23%; } .items .qty { width: 10%; } .items .rate { width: 12%; } .items .net { width: 12%; } .items .vat-rate { width: 10%; } .items .vat { width: 11%; } .items .total { width: 11%; }
        .text-right { text-align: right; } .text-center { text-align: center; }
        .item-meta { margin-top: 3px; color: var(--muted); font-size: 8.5pt; }
        .totals-layout { display: grid; grid-template-columns: 66% 34%; min-height: 27mm; border: 1px solid var(--border); border-top: 0; }
        .totals-spacer { border-right: 1px solid var(--border); }
        .summary-row { display: flex; justify-content: space-between; gap: 12px; padding: 6px 8px; border-bottom: 1px solid var(--border); }
        .summary-row > span { white-space: nowrap; }
        .summary-row > span:last-child { flex-shrink: 0; text-align: right; }
        .summary-row:last-child { border-bottom: 0; }
        .summary-row.total { padding: 8px; color: var(--primary-dark); background: var(--soft); font-size: 12pt; font-weight: 800; }
        .after-table { display: grid; grid-template-columns: 58% 38%; justify-content: space-between; gap: 4%; margin-top: 5mm; }
        .notes { min-height: 28mm; }
        .section-label { margin-bottom: 3px; color: var(--primary); font-weight: 800; }
        .signature { align-self: end; padding-top: 18mm; border-bottom: 1px solid var(--text); text-align: center; }
        .signature-label { position: relative; top: 20px; }
        .bank { margin-top: 5mm; padding: 7px 9px; border-left: 4px solid var(--primary); background: #f7f9fc; }
        .footer { margin-top: 9mm; color: var(--primary); text-align: center; }
        .footer strong { display: block; }
        @media (max-width: 700px) { .sheet { width: 100%; margin: 0; padding: 18px 12px; } .top, .panels, .after-table { grid-template-columns: 1fr; } .meta { margin-top: 0; } }
        @media print { @page { size: A4 portrait; margin: 0; } html, body { background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; } .toolbar { display: none; } .sheet { width: 210mm; min-height: 297mm; margin: 0; box-shadow: none; } }
    </style>
</head>
<body>
@php
    $company = $invoice->company;
    $customer = $invoice->customer;
    $bank = $company?->bankAccounts?->first();
    $billingAddress = $customer?->billing_address ?: collect([$customer?->address_line1, $customer?->address_line2, $customer?->city, $customer?->postcode, $customer?->country])->filter()->join("\n");
    $customerPhone = $customer?->phone ?: ($customer?->telephone_no ?: $customer?->mobile_no);
    $subtotal = (float) $invoiceTotals['subtotal'];
    $discount = (float) ($invoiceTotals['discount'] ?? 0);
    $vatTotal = (float) $invoiceTotals['vat_total'];
    $grandTotal = (float) $invoiceTotals['total'];
    $settings = $receiptSettings ?? [];
    $setting = fn (string $key, bool $default = true): bool => array_key_exists($key, $settings) ? (bool) $settings[$key] : $default;
    $showNote = $setting('receipt_show_note');
    $showPhone = $setting('receipt_show_phone');
    $showCustomer = $setting('receipt_show_customer');
    $showAddress = $setting('receipt_show_address');
    $showEmail = $setting('receipt_show_email');
    $showProductCode = $setting('receipt_show_product_code');
    $showTax = $setting('receipt_show_tax');
    $receiptNote = trim((string) ($settings['receipt_note'] ?? ''));
    $originalInvoices = $isCredit ? $invoice->salesInvoices : collect();
    if ($isCredit && $originalInvoices->isEmpty() && $invoice->salesInvoice) { $originalInvoices = collect([$invoice->salesInvoice]); }
    $originalNumbers = $originalInvoices->pluck('invoice_no')->filter()->join(', ');
    $originalDates = $originalInvoices->map(fn ($sale) => $sale->invoice_date?->format('d M Y'))->filter()->unique()->join(', ');
@endphp
<header class="toolbar"><div class="toolbar-title">{{ $documentTitle }}</div><div class="toolbar-actions"><a href="{{ url()->previous() }}">Back</a><button type="button" onclick="window.print()">Print {{ $documentTitle }}</button></div></header>
<main class="sheet">
    <section class="top">
        <div>
            <h1>{{ $documentTitle }}</h1>
            @if(filled($logoUrl))<img class="company-logo" src="{{ $logoUrl }}" alt="{{ $company?->name ?: 'Company' }} logo">@endif
            <div class="company-name">{{ $company?->legal_business_name ?: ($company?->name ?: 'Company') }}</div>
            @if($showAddress)<div>{{ $company?->address }}</div><div>{{ collect([$company?->city, $company?->postcode])->filter()->join(', ') }}</div><div>{{ $company?->country }}</div>@endif
            @if($showPhone && ($company?->business_phone_number || $company?->phone))<div>Tel: {{ $company?->business_phone_number ?: $company?->phone }}</div>@endif
            @if($showEmail && $company?->email)<div>Email: {{ $company->email }}</div>@endif
            @if($showTax && $company?->vat_number)<div>VAT Reg No.: {{ $company->vat_number }}</div>@endif
        </div>
        <table class="meta">
            <tr><th>{{ $isCredit ? 'Credit Note No.' : 'Invoice No.' }}</th><td class="colon">:</td><td class="number">{{ $documentNumber }}</td></tr>
            <tr><th>{{ $isCredit ? 'Credit Note Date' : 'Invoice Date' }}</th><td class="colon">:</td><td>{{ ($isCredit ? $invoice->return_date : $invoice->invoice_date)?->format('d M Y') ?: '—' }}</td></tr>
            @if($isCredit)<tr><th>Original Invoice No.</th><td class="colon">:</td><td>{{ $originalNumbers ?: '—' }}</td></tr><tr><th>Original Invoice Date</th><td class="colon">:</td><td>{{ $originalDates ?: '—' }}</td></tr>
            @else<tr><th>Due Date</th><td class="colon">:</td><td>{{ $invoice->due_date?->format('d M Y') ?: '—' }}</td></tr><tr><th>Reference</th><td class="colon">:</td><td>{{ $invoice->payment_note ?: '—' }}</td></tr>@endif
            <tr><th>Customer Account No.</th><td class="colon">:</td><td>{{ $customer?->account_no ?: $customer?->customer_code ?: '—' }}</td></tr>
            @if($showTax)<tr><th>Your VAT Reg No.</th><td class="colon">:</td><td>{{ $customer?->tax_number ?: ($customer?->vat_number ?: '—') }}</td></tr>@endif
        </table>
    </section>
    <section class="panels">
        <div class="panel"><div class="panel-title">Bill To:</div><div class="panel-body">@if($showCustomer)<span class="customer-name">{{ $customer?->company_name ?: ($customer?->name ?: 'Walk-in Customer') }}</span>@if($showAddress && $billingAddress)<br>{{ $billingAddress }}@endif @if($showPhone && $customerPhone)<br>{{ $customerPhone }}@endif @if($showEmail && $customer?->email)<br>{{ $customer->email }}@endif @endif</div></div>
        <div class="panel"><div class="panel-title">{{ $isCredit ? 'Reason for Return / Credit' : 'Delivery / Payment Details' }}</div><div class="panel-body">{{ $isCredit ? (strip_tags((string) $invoice->notes) ?: 'Credit issued for returned goods.') : ($invoice->payment_note ?: 'Please refer to the invoice terms.') }}</div></div>
    </section>
    <table class="items"><thead><tr><th class="code">Item Code</th><th class="description">Description</th><th class="qty">{{ $isCredit ? 'Quantity Returned' : 'Quantity' }}</th><th class="rate">Unit Price<br>({{ app_currency_symbol() }})</th><th class="net">Net Amount<br>({{ app_currency_symbol() }})</th>@if($showTax)<th class="vat-rate">VAT Rate<br>(%)</th><th class="vat">VAT Amount<br>({{ app_currency_symbol() }})</th>@endif<th class="total">Total Amount<br>({{ app_currency_symbol() }})</th></tr></thead><tbody>
        @forelse($invoice->items as $item)
            @php $calculated = $invoiceTotals['items'][$loop->index] ?? []; $gross = round((float) $item->qty * (float) $item->rate, 2); $lineDiscount = $subtotal > 0 ? round($discount * $gross / $subtotal, 2) : 0; $net = max(0, $gross - $lineDiscount); $tax = (float) ($calculated['vat_amount'] ?? $item->vat_amount); $lineTotal = $net + $tax; @endphp
            <tr><td>{{ $showProductCode ? ($item->productItem?->item_code ?: '—') : $loop->iteration }}</td><td><strong>{{ $item->description ?: ($item->productItem?->name ?: 'Item') }}</strong></td><td class="text-center">{{ rtrim(rtrim(number_format((float) $item->qty, 3), '0'), '.') }}</td><td class="text-right">{{ number_format((float) $item->rate, 2) }}</td><td class="text-right">{{ number_format($net, 2) }}</td>@if($showTax)<td class="text-center">{{ rtrim(rtrim(number_format((float) $item->vat_rate, 2), '0'), '.') }}</td><td class="text-right">{{ number_format($tax, 2) }}</td>@endif<td class="text-right">{{ number_format($lineTotal, 2) }}</td></tr>
        @empty<tr><td colspan="{{ $showTax ? 8 : 6 }}">No document items</td></tr>@endforelse
    </tbody></table>
    <section class="totals-layout"><div class="totals-spacer"></div><div><div class="summary-row"><span>Total Net Amount</span><span>{{ app_money($subtotal - $discount) }}</span></div>@if($showTax)<div class="summary-row"><span>Total VAT Amount</span><span>{{ app_money($vatTotal) }}</span></div>@endif<div class="summary-row total"><span>{{ $isCredit ? 'CREDIT TOTAL' : 'INVOICE TOTAL' }}</span><span>{{ app_money($grandTotal) }}</span></div>@if(!$isCredit && $paidAmount !== null)<div class="summary-row"><span>Paid / Amount Due</span><span>{{ app_money((float) $paidAmount) }} / {{ app_money((float) $dueAmount) }}</span></div>@endif</div></section>
    <section class="after-table"><div class="notes">@if($showNote)<div class="section-label">Notes:</div>@if(filled($invoice->notes))<div>{{ \Filament\Forms\Components\RichEditor\RichContentRenderer::make($invoice->notes) }}</div>@endif @if($receiptNote !== '')<div>{{ \Filament\Forms\Components\RichEditor\RichContentRenderer::make($receiptNote) }}</div>@endif @endif</div><div class="signature"><span class="signature-label">Authorised Signature</span></div></section>
    @if(!$isCredit && $bank)<section class="bank"><span class="section-label">Company Bank Details:</span> {{ $bank->bank_name }} · {{ $bank->account_name }} · A/C {{ $bank->account_number }} @if($bank->sort_code) · Sort {{ $bank->sort_code }}@endif</section>@endif
    <footer class="footer"><strong>Thank you for your business.</strong>@if($company?->company_house_number)<div>Registered Company No. {{ $company->company_house_number }}</div>@endif</footer>
</main>
</body>
</html>
