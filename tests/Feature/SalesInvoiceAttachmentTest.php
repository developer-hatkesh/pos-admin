<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use App\Mail\SalesInvoiceNotification;
use App\Models\Company;
use App\Models\Customer;
use App\Models\SalesInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SalesInvoiceAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_multiple_invoice_attachments_are_stored_in_s3_and_added_to_the_email(): void
    {
        Storage::fake('s3');

        $company = Company::factory()->create();
        $customer = Customer::factory()->for($company)->create();
        $invoice = SalesInvoice::query()->create([
            'company_id' => $company->id,
            'invoice_no' => 'SI-ATTACHMENTS',
            'customer_id' => $customer->id,
            'invoice_date' => today(),
            'subtotal' => 0,
            'discount' => 0,
            'vat_total' => 0,
            'shipping' => 0,
            'total' => 0,
            'status' => InvoiceStatus::Draft,
        ]);

        Storage::disk('s3')->put('sales-invoices/tmp/terms.pdf', '%PDF-1.4 test');
        Storage::disk('s3')->put('sales-invoices/tmp/order.pdf', '%PDF-1.4 test');

        SalesInvoiceResource::syncAttachment($invoice, [
            'sales-invoices/tmp/terms.pdf',
            'sales-invoices/tmp/order.pdf',
        ], SalesInvoice::ATTACHMENTS_COLLECTION);

        $media = $invoice->fresh()->getMedia(SalesInvoice::ATTACHMENTS_COLLECTION);

        $this->assertCount(2, $media);
        $this->assertSame([
            "Invoice/{$invoice->id}/terms.pdf",
            "Invoice/{$invoice->id}/order.pdf",
        ], $media->map->getPathRelativeToRoot()->all());
        $this->assertCount(3, (new SalesInvoiceNotification($invoice->fresh()))->attachments());
    }
}
