<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $invoice->invoice_no }}</title>
    <style>
        :root {
            color: #111827;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
        }

        body {
            margin: 0;
            background: #f3f4f6;
        }

        .invoice-page {
            width: min(210mm, calc(100% - 32px));
            min-height: 297mm;
            margin: 16px auto;
            padding: 18mm;
            background: #fff;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.12);
        }

        .invoice-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            width: min(210mm, calc(100% - 32px));
            margin: 16px auto 0;
        }

        .invoice-actions button,
        .invoice-actions a {
            border: 0;
            border-radius: 6px;
            background: #1e40af;
            color: #fff;
            padding: 10px 16px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .invoice-actions a {
            background: #64748b;
        }

        .invoice-header,
        .invoice-meta,
        .invoice-summary-row {
            display: flex;
            justify-content: space-between;
            gap: 24px;
        }

        .invoice-header {
            align-items: flex-start;
            border-bottom: 2px solid #111827;
            padding-bottom: 18px;
        }

        h1 {
            margin: 0;
            font-size: 32px;
            letter-spacing: 0;
        }

        h2 {
            margin: 0 0 8px;
            font-size: 16px;
        }

        p {
            margin: 3px 0;
        }

        .muted {
            color: #6b7280;
        }

        .invoice-meta {
            margin: 28px 0;
        }

        .invoice-box {
            min-width: 0;
        }

        .invoice-box--right {
            text-align: right;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            border-bottom: 1px solid #111827;
            padding: 10px 8px;
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
        }

        td {
            border-bottom: 1px solid #e5e7eb;
            padding: 10px 8px;
            vertical-align: top;
        }

        .text-right {
            text-align: right;
        }

        .invoice-totals {
            width: min(320px, 100%);
            margin: 28px 0 0 auto;
        }

        .invoice-summary-row {
            border-bottom: 1px solid #e5e7eb;
            padding: 8px 0;
        }

        .invoice-summary-row strong {
            font-size: 16px;
        }

        .invoice-total {
            border-bottom: 2px solid #111827;
            font-size: 17px;
            font-weight: 800;
        }

        @media print {
            body {
                background: #fff;
            }

            .invoice-actions {
                display: none;
            }

            .invoice-page {
                width: auto;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }

            @page {
                margin: 14mm;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-actions">
        <a href="{{ url('/admin/pos-sales') }}">Back to POS</a>
        <button type="button" onclick="window.print()">Print</button>
    </div>

    <main class="invoice-page">
        <header class="invoice-header">
            <div>
                <h1>Invoice</h1>
                <p class="muted">{{ $invoice->invoice_no }}</p>
            </div>
            <div class="invoice-box invoice-box--right">
                <h2>{{ $invoice->company?->name ?: 'Company' }}</h2>
                <p>{{ $invoice->company?->address }}</p>
                <p>{{ collect([$invoice->company?->city, $invoice->company?->postcode])->filter()->join(', ') }}</p>
                <p>{{ $invoice->company?->phone }}</p>
                <p>{{ $invoice->company?->email }}</p>
                @if ($invoice->company?->vat_number)
                    <p>VAT: {{ $invoice->company->vat_number }}</p>
                @endif
            </div>
        </header>

        <section class="invoice-meta">
            <div class="invoice-box">
                <h2>Bill To</h2>
                <p><strong>{{ $invoice->customer?->name ?: 'N/A' }}</strong></p>
                <p>{{ $invoice->customer?->address_line1 }}</p>
                <p>{{ collect([$invoice->customer?->city, $invoice->customer?->postcode])->filter()->join(', ') }}</p>
                <p>{{ $invoice->customer?->phone }}</p>
                <p>{{ $invoice->customer?->email }}</p>
            </div>
            <div class="invoice-box invoice-box--right">
                <p><strong>Date:</strong> {{ $invoice->invoice_date?->format('d M Y') }}</p>
                @if ($invoice->due_date)
                    <p><strong>Due:</strong> {{ $invoice->due_date->format('d M Y') }}</p>
                @endif
                <p><strong>Status:</strong> {{ $invoice->status instanceof \BackedEnum ? ucfirst($invoice->status->value) : ucfirst((string) $invoice->status) }}</p>
            </div>
        </section>

        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Rate</th>
                    <th class="text-right">VAT</th>
                    <th class="text-right">Line Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->items as $item)
                    <tr>
                        <td>
                            <strong>{{ $item->description ?: $item->productItem?->name }}</strong>
                            @if ($item->productItem?->item_code)
                                <p class="muted">Code: {{ $item->productItem->item_code }}</p>
                            @endif
                        </td>
                        <td class="text-right">{{ rtrim(rtrim(number_format((float) $item->qty, 3), '0'), '.') }}</td>
                        <td class="text-right">{{ app_money((float) $item->rate) }}</td>
                        <td class="text-right">{{ app_money((float) $item->vat_amount) }}</td>
                        <td class="text-right">{{ app_money((float) $item->line_total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <section class="invoice-totals">
            <div class="invoice-summary-row">
                <span>Subtotal</span>
                <span>{{ app_money((float) $invoice->subtotal) }}</span>
            </div>
            <div class="invoice-summary-row">
                <span>Discount</span>
                <span>{{ app_money((float) $invoice->discount) }}</span>
            </div>
            <div class="invoice-summary-row">
                <span>VAT</span>
                <span>{{ app_money((float) $invoice->vat_total) }}</span>
            </div>
            <div class="invoice-summary-row invoice-total">
                <span>Total</span>
                <strong>{{ app_money((float) $invoice->total) }}</strong>
            </div>
            <div class="invoice-summary-row">
                <span>Paid</span>
                <span>{{ app_money((float) ($paidAmount ?? 0)) }}</span>
            </div>
            <div class="invoice-summary-row invoice-total">
                <span>Amount Due</span>
                <strong>{{ app_money((float) ($dueAmount ?? 0)) }}</strong>
            </div>
        </section>
    </main>
</body>
</html>
