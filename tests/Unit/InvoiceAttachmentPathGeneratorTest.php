<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\SalesInvoice;
use App\Support\MediaLibrary\ProductItemPathGenerator;
use PHPUnit\Framework\TestCase;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class InvoiceAttachmentPathGeneratorTest extends TestCase
{
    public function test_sales_invoice_attachments_use_the_required_s3_directory(): void
    {
        $media = new Media;
        $media->model_id = 42;
        $media->collection_name = SalesInvoice::ATTACHMENTS_COLLECTION;

        $this->assertSame('Invoice/42/', (new ProductItemPathGenerator)->getPath($media));
    }
}
