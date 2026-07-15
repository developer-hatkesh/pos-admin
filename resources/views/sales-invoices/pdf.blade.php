<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_no }}</title>
    <style>
        @page { margin: 25px; }
        body { margin: 0; color: #131b2e; font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.45; }
        table { width: 100%; border-collapse: collapse; }
        .header td { padding: 12px; vertical-align: top; border-bottom: 1px solid #c5c5d3; }
        .company { color: #1e3a8a; font-weight: bold; text-align: right; }
        .company-name { font-size: 15px; }
        .title { color: #1e3a8a; font-size: 17px; font-weight: bold; letter-spacing: 1px; }
        .meta td, .addresses td { padding: 10px 12px; vertical-align: top; border-bottom: 1px solid #c5c5d3; }
        .addresses td + td { border-left: 1px solid #c5c5d3; }
        .label, h3 { margin: 0 0 5px; color: #1e3a8a; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .items th { padding: 8px 6px; color: #fff; background: #1e3a8a; text-align: left; }
        .items td { padding: 8px 6px; border-bottom: 1px solid #c5c5d3; vertical-align: top; }
        .right { text-align: right !important; }
        .bottom td { width: 50%; padding: 12px; vertical-align: top; }
        .bank { background: #f2f3ff; }
        .summary td { padding: 4px 0; }
        .summary .grand td { padding-top: 7px; color: #1e3a8a; border-top: 1px solid #c5c5d3; font-weight: bold; }
        .notes { padding: 10px 12px; background: #eaedff; }
        .footer { margin-top: 20px; padding-top: 8px; color: #666; border-top: 1px solid #c5c5d3; text-align: center; }
    </style>
</head>
<body>
@php
    $company = $invoice->company;
    $customer = $invoice->customer;
    $bank = $company?->bankAccounts?->first();
    $billingAddress = $customer?->billing_address ?: collect([$customer?->address_line1, $customer?->address_line2, $customer?->city, $customer?->postcode, $customer?->country])->filter()->join(', ');
    $shippingAddress = $customer?->delivery_address ?: $billingAddress;
    $customerVat = $customer?->tax_number ?: $customer?->vat_number;
    $subtotal = (float) $invoiceTotals['subtotal'];
    $discount = (float) $invoiceTotals['discount'];
@endphp
<table class="header"><tr><td><div class="title">COMMERCIAL INVOICE</div></td><td class="company"><div class="company-name">{{ $company?->legal_business_name ?: ($company?->name ?: 'Company') }}</div><div>{{ $company?->address }}</div><div>{{ collect([$company?->city, $company?->postcode, $company?->country])->filter()->join(', ') }}</div>@if($company?->email)<div>{{ $company->email }}</div>@endif @if($company?->vat_number)<div>VAT No: {{ $company->vat_number }}</div>@endif</td></tr></table>
<table class="meta"><tr><td><span class="label">Invoice Number</span><strong>{{ $invoice->invoice_no }}</strong></td><td><span class="label">Invoice Date</span>{{ $invoice->invoice_date?->format('d M Y') }}</td><td><span class="label">Due Date</span>{{ $invoice->due_date?->format('d M Y') ?: '—' }}</td><td><span class="label">Reference</span>{{ $invoice->payment_note ?: '—' }}</td></tr></table>
<table class="addresses"><tr><td><h3>Invoice To</h3><strong>{{ $customer?->company_name ?: ($customer?->name ?: 'Customer') }}</strong><br>{{ $billingAddress }}@if($customer?->email)<br>{{ $customer->email }}@endif @if($customerVat)<br>VAT No: {{ $customerVat }}@endif</td><td><h3>Ship To</h3><strong>{{ $customer?->company_name ?: ($customer?->name ?: 'Customer') }}</strong><br>{{ $shippingAddress ?: 'Same as invoice address' }}</td></tr></table>
<table class="items"><thead><tr><th>Sr.</th><th>Description</th><th class="right">Qty</th><th class="right">Unit Price</th><th class="right">Discount</th><th class="right">Tax</th><th class="right">Line Total</th></tr></thead><tbody>
@forelse($invoice->items as $item)
@php
    $gross = round((float) $item->qty * (float) $item->rate, 2);
    $lineDiscount = $subtotal > 0 ? round($discount * $gross / $subtotal, 2) : 0;
    $computedLine = $invoiceTotals['items'][$loop->index] ?? [];
    $lineTax = (float) ($computedLine['vat_amount'] ?? $item->vat_amount);
    $lineTotal = (float) ($computedLine['line_total'] ?? $item->line_total) - $lineDiscount;
@endphp
<tr><td>{{ $loop->iteration }}</td><td><strong>{{ $item->description ?: ($item->productItem?->name ?: 'Item') }}</strong>@if($item->productItem?->item_code)<br>Code: {{ $item->productItem->item_code }}@endif</td><td class="right">{{ rtrim(rtrim(number_format((float) $item->qty, 3), '0'), '.') }}</td><td class="right">{{ app_money((float) $item->rate) }}</td><td class="right">{{ app_money($lineDiscount) }}</td><td class="right">{{ app_money($lineTax) }}</td><td class="right"><strong>{{ app_money($lineTotal) }}</strong></td></tr>
@empty<tr><td colspan="7">No invoice items</td></tr>@endforelse
</tbody></table>
<table class="bottom"><tr><td class="bank"><h3>Company Bank Details</h3>@if($bank)<strong>{{ $bank->bank_name }}</strong><br>Account Name: {{ $bank->account_name }}<br>Account No: {{ $bank->account_number }}@if($bank->sort_code)<br>Sort Code: {{ $bank->sort_code }}@endif @else Bank details are available on request. @endif</td><td><table class="summary"><tr><td>Subtotal</td><td class="right">{{ app_money($subtotal) }}</td></tr><tr><td>Discount</td><td class="right">{{ app_money($discount) }}</td></tr><tr><td>Tax</td><td class="right">{{ app_money((float) $invoiceTotals['vat_total']) }}</td></tr><tr class="grand"><td>Grand Total</td><td class="right">{{ app_money((float) $invoiceTotals['total']) }}</td></tr><tr><td>Paid</td><td class="right">{{ app_money((float) $paidAmount) }}</td></tr><tr class="grand"><td>Amount Due</td><td class="right">{{ app_money((float) $dueAmount) }}</td></tr></table></td></tr></table>
@if(filled($invoice->notes))<div class="notes"><span class="label">Notes</span>{{ $invoice->notes }}</div>@endif
<div class="footer">This is a system-generated invoice and no signature is required.</div>
</body>
</html>
