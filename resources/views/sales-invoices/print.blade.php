<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Commercial Invoice {{ $invoice->invoice_no }}</title>
    <style>
        * { box-sizing: border-box; }
        :root { color: #111; font-family: Arial, Helvetica, sans-serif; font-size: 12px; }
        body { margin: 0; background: #eef0f3; }
        .actions { display: flex; justify-content: flex-end; gap: 8px; width: 210mm; max-width: calc(100% - 24px); margin: 14px auto 0; }
        .actions a, .actions button { border: 0; border-radius: 5px; padding: 9px 16px; color: #fff; background: #1e40af; font-weight: 700; text-decoration: none; cursor: pointer; }
        .actions a { background: #64748b; }
        .sheet { display: flex; flex-direction: column; width: 210mm; min-height: 297mm; margin: 14px auto; background: #fff; box-shadow: 0 8px 30px rgba(0,0,0,.14); }
        .title { border-bottom: 3px solid #111; padding: 8px; text-align: center; font-size: 14px; font-weight: 700; text-transform: uppercase; }
        .company { display: grid; grid-template-columns: 37% 63%; min-height: 112px; border-bottom: 3px solid #111; }
        .logo-wrap { display: flex; align-items: center; justify-content: center; padding: 12px 22px; }
        .logo { max-width: 165px; max-height: 80px; object-fit: contain; }
        .logo-placeholder { display: flex; align-items: center; justify-content: center; width: 100px; height: 72px; border: 2px solid #111; font-size: 14px; font-weight: 700; }
        .company-details { display: flex; flex-direction: column; align-items: flex-end; justify-content: center; padding: 12px 24px; font-size: 13px; line-height: 1.38; text-align: right; }
        .company-name { margin-bottom: 2px; font-size: 15px; font-weight: 800; text-transform: uppercase; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; min-height: 72px; border-bottom: 3px solid #111; padding: 12px; }
        .meta-block:last-child { justify-self: end; min-width: 235px; }
        .meta-row { display: grid; grid-template-columns: 105px 10px 1fr; line-height: 1.65; }
        .parties { display: grid; grid-template-columns: 1fr 1fr; min-height: 132px; border-bottom: 3px solid #888; }
        .party:first-child { border-right: 3px solid #888; }
        .party-title { border-bottom: 3px solid #111; padding: 8px 12px; font-size: 14px; font-weight: 800; text-transform: uppercase; }
        .party-content { padding: 15px 26px; color: #555; font-size: 13px; line-height: 1.45; }
        .party-content strong { color: #222; }
        table { width: 100%; border-collapse: collapse; }
        .items { height: 100%; table-layout: fixed; }
        .items th, .items td { border-right: 3px solid #888; }
        .items th:first-child, .items td:first-child { border-left: 3px solid #888; }
        .items th { height: 42px; border-bottom: 3px solid #888; padding: 8px; font-size: 13px; text-align: center; }
        .items td { height: 36px; padding: 8px; vertical-align: top; }
        .items .number { width: 6%; text-align: center; }
        .items .qty { width: 13%; text-align: right; }
        .items .money { width: 21%; text-align: right; }
        .items tbody tr:last-child { height: 100%; }
        .item-code { margin-top: 3px; color: #777; font-size: 10px; }
        .items-body { height: 255px; min-height: 255px; border-bottom: 0; }
        .bottom { display: grid; grid-template-columns: 58% 42%; min-height: 172px; border-top: 3px solid #888; border-bottom: 3px solid #888; }
        .bank { padding: 35px 36px 15px; color: #555; font-size: 12px; line-height: 1.5; }
        .bank-title { margin-bottom: 5px; color: #222; font-weight: 800; }
        .totals { border-left: 3px solid #888; }
        .totals-row { display: grid; grid-template-columns: 42% 58%; min-height: 29px; border-bottom: 3px solid #888; }
        .totals-row:last-child { border-bottom: 0; }
        .totals-label { border-right: 3px solid #888; padding: 6px 8px; font-weight: 800; text-align: right; }
        .totals-value { padding: 6px 10px; text-align: right; }
        .grand { font-size: 13px; }
        .notes { min-height: 115px; padding: 30px; color: #555; }
        .notes strong { color: #222; }
        .footer { display: grid; grid-template-columns: 1fr auto; gap: 20px; margin-top: auto; border-top: 3px solid #888; padding: 10px 18px; font-size: 10px; }
        .footer-company { font-size: 12px; font-weight: 800; }
        .status-strip { display: flex; justify-content: flex-end; gap: 20px; padding: 5px 12px; color: #555; font-size: 10px; }
        @media (max-width: 820px) { .sheet { width: 100%; margin: 0; } .actions { width: auto; } }
        @media print {
            body { background: #fff; }
            .actions { display: none; }
            .sheet { width: 100%; min-height: 285mm; margin: 0; box-shadow: none; }
            @page { size: A4 portrait; margin: 6mm; }
        }
    </style>
</head>
<body>
@php
    $company = $invoice->company;
    $customer = $invoice->customer;
    $shippingItems = $invoice->items->filter(fn ($item) => strtolower(trim((string) $item->description)) === 'shipping');
    $displayItems = $invoice->items->reject(fn ($item) => $shippingItems->contains($item));
    $shipping = (float) $shippingItems->sum(fn ($item) => round((float) $item->qty * (float) $item->rate, 2));
    $displaySubtotal = max(0, (float) $invoiceTotals['subtotal'] - $shipping);
    $bank = $company?->bankAccounts?->first();
    $billingAddress = $customer?->billing_address ?: collect([$customer?->address_line1, $customer?->address_line2, $customer?->city, $customer?->postcode, $customer?->country])->filter()->join(', ');
    $shippingAddress = $customer?->delivery_address ?: $billingAddress;
    $status = $invoice->status instanceof \BackedEnum ? $invoice->status->value : (string) $invoice->status;
@endphp
    <div class="actions">
        <a href="{{ url()->previous() }}">Back</a>
        <button type="button" onclick="window.print()">Print Invoice</button>
    </div>

    <main class="sheet">
        <div class="title">Commercial Invoice</div>
        <section class="company">
            <div class="logo-wrap">
                @if (filled($logoUrl))
                    <img class="logo" src="{{ $logoUrl }}" alt="{{ $company?->name }} logo">
                @else
                    <div class="logo-placeholder">{{ $company?->name }}</div>
                @endif
            </div>
            <div class="company-details">
                <div class="company-name">{{ $company?->legal_business_name ?: ($company?->name ?: 'Company') }}</div>
                @if ($company?->address)<div>{{ $company->address }}</div>@endif
                @if (collect([$company?->city, $company?->postcode, $company?->country])->filter()->isNotEmpty())
                    <div>{{ collect([$company?->city, $company?->postcode, $company?->country])->filter()->join(', ') }}</div>
                @endif
                @if ($company?->email)<div><strong>Email:</strong> {{ $company->email }}</div>@endif
                @if ($company?->phone || $company?->business_phone_number)<div><strong>Contact:</strong> {{ $company?->business_phone_number ?: $company?->phone }}</div>@endif
                @if ($company?->vat_number)<div><strong>VAT No:</strong> {{ $company->vat_number }}</div>@endif
            </div>
        </section>

        <section class="meta">
            <div class="meta-block">
                <div class="meta-row"><span>Invoice No</span><span>:</span><strong>{{ $invoice->invoice_no }}</strong></div>
                <div class="meta-row"><span>Invoice Date</span><span>:</span><strong>{{ $invoice->invoice_date?->format('d-M-y') }}</strong></div>
                @if ($invoice->due_date)<div class="meta-row"><span>Due Date</span><span>:</span><span>{{ $invoice->due_date->format('d-M-y') }}</span></div>@endif
            </div>
            <div class="meta-block">
                <div class="meta-row"><span>Reference No</span><span>:</span><span>{{ $invoice->payment_note ?: '—' }}</span></div>
                <div class="meta-row"><span>Invoice Currency</span><span>:</span><span>{{ config('app.currency', 'GBP') }}</span></div>
            </div>
        </section>

        <section class="parties">
            <div class="party">
                <div class="party-title">Invoice To</div>
                <div class="party-content">
                    <strong>{{ $customer?->company_name ?: ($customer?->name ?: 'Walk-in Customer') }}</strong><br>
                    @if ($billingAddress){{ $billingAddress }}<br>@endif
                    @if ($customer?->phone){{ $customer->phone }}<br>@endif
                    @if ($customer?->email){{ $customer->email }}@endif
                </div>
            </div>
            <div class="party">
                <div class="party-title">Ship To</div>
                <div class="party-content">
                    <strong>{{ $customer?->company_name ?: ($customer?->name ?: 'Walk-in Customer') }}</strong><br>
                    @if ($shippingAddress){{ $shippingAddress }}@else Same as invoice address @endif
                </div>
            </div>
        </section>

        <div class="items-body">
            <table class="items">
                <thead><tr><th class="number">Sr.</th><th>Description</th><th class="qty">Qty</th><th class="money">Unit Price</th><th class="money">Total</th></tr></thead>
                <tbody>
                @forelse ($displayItems as $item)
                    <tr>
                        <td class="number">{{ $loop->iteration }}</td>
                        <td><strong>{{ $item->description ?: ($item->productItem?->name ?: 'Item') }}</strong>@if ($item->productItem?->item_code)<div class="item-code">Code: {{ $item->productItem->item_code }}</div>@endif</td>
                        <td class="qty">{{ rtrim(rtrim(number_format((float) $item->qty, 3), '0'), '.') }}</td>
                        <td class="money">{{ app_money((float) $item->rate) }}</td>
                        <td class="money">{{ app_money((float) $item->line_total + (float) $item->vat_amount) }}</td>
                    </tr>
                @empty
                    <tr><td class="number">1</td><td>No invoice items</td><td></td><td></td><td></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <section class="bottom">
            <div class="bank">
                <div class="bank-title">Company Bank Details</div>
                @if ($bank)
                    <div>{{ $bank->bank_name }} — {{ $bank->account_name }}</div>
                    <div>Account No: {{ $bank->account_number }}@if ($bank->sort_code) &nbsp; Sort Code: {{ $bank->sort_code }}@endif</div>
                @else
                    <div>Bank details are available on request.</div>
                @endif
            </div>
            <div class="totals">
                <div class="totals-row"><div class="totals-label">Sub Total :</div><div class="totals-value">{{ app_money($displaySubtotal) }}</div></div>
                <div class="totals-row"><div class="totals-label">Tax :</div><div class="totals-value">{{ app_money((float) $invoiceTotals['vat_total']) }}</div></div>
                <div class="totals-row"><div class="totals-label">Discount :</div><div class="totals-value">{{ app_money((float) $invoiceTotals['discount']) }}</div></div>
                <div class="totals-row"><div class="totals-label">Shipping :</div><div class="totals-value">{{ app_money($shipping) }}</div></div>
                <div class="totals-row grand"><div class="totals-label">Grand Total :</div><div class="totals-value"><strong>{{ app_money((float) $invoiceTotals['total']) }}</strong></div></div>
                <div class="totals-row"><div class="totals-label">Paid :</div><div class="totals-value">{{ app_money((float) ($paidAmount ?? 0)) }}</div></div>
                <div class="totals-row"><div class="totals-label">Amount Due :</div><div class="totals-value"><strong>{{ app_money((float) ($dueAmount ?? 0)) }}</strong></div></div>
            </div>
        </section>

        <section class="notes"><strong>Note:</strong><br>{{ $invoice->notes ?: 'Thank you for your business.' }}</section>
        <div class="status-strip"><span>Status: {{ ucfirst($status) }}</span></div>
        <footer class="footer">
            <span>This is a system generated invoice and no signature is required.</span>
            <span class="footer-company">For {{ $company?->legal_business_name ?: ($company?->name ?: 'Company') }}</span>
        </footer>
    </main>
</body>
</html>
