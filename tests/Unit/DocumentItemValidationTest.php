<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Filament\Resources\PurchaseReturns\PurchaseReturnResource;
use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use App\Filament\Resources\SalesReturns\SalesReturnResource;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DocumentItemValidationTest extends TestCase
{
    public function test_totals_can_be_calculated_for_legacy_lines_without_product_ids(): void
    {
        $data = ['items' => [['rate' => 10, 'qty' => 2, 'vat_rate' => 20]]];

        $this->assertSame(24.0, (float) SalesInvoiceResource::calculateTotalsFromData($data)['total']);
        $this->assertSame(24.0, (float) PurchaseInvoiceResource::calculateTotalsFromData($data)['total']);
    }

    #[DataProvider('emptyDocumentProvider')]
    public function test_documents_require_at_least_one_item(callable $prepare): void
    {
        try {
            $prepare(['items' => []]);
            $this->fail('Expected item validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['Please add at least one item.'],
                $exception->errors()['items'] ?? [],
            );
        }
    }

    public static function emptyDocumentProvider(): array
    {
        return [
            'sales invoice' => [fn (array $data) => SalesInvoiceResource::validateItemsForSave($data)],
            'purchase invoice' => [fn (array $data) => PurchaseInvoiceResource::validateItemsForSave($data)],
            'credit note' => [fn (array $data): array => SalesReturnResource::prepareDataForSave($data)],
            'debit note' => [fn (array $data): array => PurchaseReturnResource::prepareDataForSave($data)],
        ];
    }

    #[DataProvider('nonPositiveLineProvider')]
    public function test_document_lines_require_positive_price_and_quantity(
        callable $prepare,
        array $item,
        string $field,
        string $message,
    ): void {
        try {
            $prepare(['items' => [$item]]);
            $this->fail('Expected positive number validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame([$message], $exception->errors()["items.0.{$field}"] ?? []);
        }
    }

    public static function nonPositiveLineProvider(): array
    {
        $documents = [
            'sales invoice' => [fn (array $data) => SalesInvoiceResource::validateItemsForSave($data), 'product_item_id'],
            'purchase invoice' => [fn (array $data) => PurchaseInvoiceResource::validateItemsForSave($data), 'product_item_id'],
            'credit note' => [fn (array $data): array => SalesReturnResource::prepareDataForSave($data), 'sales_invoice_item_id'],
            'debit note' => [fn (array $data): array => PurchaseReturnResource::prepareDataForSave($data), 'purchase_invoice_item_id'],
        ];
        $cases = [];

        foreach ($documents as $name => [$prepare, $identifier]) {
            $cases["{$name} price"] = [
                $prepare,
                [$identifier => 1, 'rate' => 0, 'qty' => 1],
                'rate',
                'Price must be greater than zero.',
            ];
            $cases["{$name} quantity"] = [
                $prepare,
                [$identifier => 1, 'rate' => 1, 'qty' => 0],
                'qty',
                'Quantity must be greater than zero.',
            ];
        }

        return $cases;
    }
}
