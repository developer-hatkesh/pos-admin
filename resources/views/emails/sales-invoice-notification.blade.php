<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Invoice {{ $invoice->invoice_no }}</title></head>
<body style="margin:0;padding:0;background:#f2f3ff;color:#131b2e;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;">
@php
    $company = $invoice->company;
    $customer = $invoice->customer;
    $companyName = $company?->legal_business_name ?: ($company?->name ?: config('app.name'));
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f3ff;padding:28px 12px;"><tr><td align="center">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:#ffffff;border-collapse:collapse;border-top:5px solid #1e3a8a;">
        <tr><td style="padding:30px 34px 20px;text-align:center;">
            <div style="color:#1e3a8a;font-size:22px;font-weight:800;letter-spacing:.04em;">{{ $companyName }}</div>
            <div style="margin-top:5px;color:#5f6470;">INVOICE NOTIFICATION</div>
        </td></tr>
        <tr><td style="padding:10px 34px 24px;">
            <p style="margin:0 0 16px;">Dear {{ $customer?->contact_person ?: ($customer?->company_name ?: 'Customer') }},</p>
            <p style="margin:0 0 20px;">Please find your invoice from {{ $companyName }} attached as a PDF.</p>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;background:#f2f3ff;">
                <tr><td style="padding:11px 14px;color:#1e3a8a;font-weight:700;">Invoice number</td><td style="padding:11px 14px;text-align:right;font-weight:700;">{{ $invoice->invoice_no }}</td></tr>
                <tr><td style="padding:11px 14px;color:#1e3a8a;font-weight:700;border-top:1px solid #d8ddec;">Invoice date</td><td style="padding:11px 14px;text-align:right;border-top:1px solid #d8ddec;">{{ $invoice->invoice_date?->format('d M Y') }}</td></tr>
                @if($invoice->due_date)<tr><td style="padding:11px 14px;color:#1e3a8a;font-weight:700;border-top:1px solid #d8ddec;">Due date</td><td style="padding:11px 14px;text-align:right;border-top:1px solid #d8ddec;">{{ $invoice->due_date->format('d M Y') }}</td></tr>@endif
                <tr><td style="padding:11px 14px;color:#1e3a8a;font-weight:700;border-top:1px solid #d8ddec;">Invoice total</td><td style="padding:11px 14px;text-align:right;font-weight:700;border-top:1px solid #d8ddec;">{{ app_money((float) $invoiceTotals['total']) }}</td></tr>
                <tr><td style="padding:11px 14px;color:#1e3a8a;font-weight:700;border-top:1px solid #d8ddec;">Amount due</td><td style="padding:11px 14px;text-align:right;color:#1e3a8a;font-weight:800;border-top:1px solid #d8ddec;">{{ app_money((float) $dueAmount) }}</td></tr>
            </table>
            <p style="margin:22px 0 0;">If you have any questions about this invoice, please reply to this email.</p>
            <p style="margin:20px 0 0;">Kind regards,<br><strong>{{ $companyName }}</strong></p>
        </td></tr>
        <tr><td style="padding:16px 34px;color:#6b7280;background:#eaedff;text-align:center;font-size:12px;">This email was generated automatically. A PDF copy of the invoice is attached.</td></tr>
    </table>
</td></tr></table>
</body>
</html>
