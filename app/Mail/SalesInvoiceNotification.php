<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\SalesReturnStatus;
use App\Enums\VoucherStatus;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\VoucherAllocation;
use App\Services\Settings\AppSettings;
use App\Support\DocumentTotals;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class SalesInvoiceNotification extends Mailable
{
    use Queueable, SerializesModels;

    /** @var array<string, mixed> */
    private array $invoiceData;

    public function __construct(public SalesInvoice $invoice)
    {
        $this->invoice->loadMissing(['company.bankAccounts', 'customer', 'items.productItem']);

        $invoiceTotals = DocumentTotals::calculate([
            'items' => $this->invoice->items->toArray(),
            'discount' => $this->invoice->discount,
            'shipping' => $this->invoice->shipping,
        ]);
        $paid = (float) VoucherAllocation::query()
            ->where('sales_invoice_id', $this->invoice->id)
            ->whereHas('voucher', fn ($query) => $query->where('status', VoucherStatus::Posted->value))
            ->sum('amount');
        $returned = (float) SalesReturn::withoutGlobalScopes()
            ->where('sales_invoice_id', $this->invoice->id)
            ->where('status', SalesReturnStatus::Posted->value)
            ->sum('total');

        $this->invoiceData = [
            'invoice' => $this->invoice,
            'invoiceTotals' => $invoiceTotals,
            'paidAmount' => $paid,
            'dueAmount' => max(0, (float) $invoiceTotals['total'] - $paid - $returned),
            'logoUrl' => AppSettings::storeLogoUrl(),
        ];
    }

    public function envelope(): Envelope
    {
        $companyName = $this->invoice->company?->legal_business_name ?: $this->invoice->company?->name;

        return new Envelope(
            subject: 'Invoice '.$this->invoice->invoice_no.' from '.($companyName ?: config('app.name')),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.sales-invoice-notification',
            with: $this->invoiceData,
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $data = $this->invoiceData;
        $filename = 'invoice-'.Str::slug((string) $this->invoice->invoice_no).'.pdf';

        $attachments = [
            Attachment::fromData(
                fn (): string => Pdf::loadView('sales-invoices.pdf', $data)->setPaper('a4')->output(),
                $filename,
            )->withMime('application/pdf'),
        ];

        foreach ($this->invoice->getMedia(SalesInvoice::ATTACHMENTS_COLLECTION) as $media) {
            $attachments[] = Attachment::fromStorageDisk('s3', $media->getPathRelativeToRoot())
                ->as($media->file_name)
                ->withMime($media->mime_type);
        }

        return $attachments;
    }
}
