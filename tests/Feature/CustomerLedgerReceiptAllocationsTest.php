<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\ReceiptVouchers\ReceiptVoucherResource;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Customer;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Reports\CustomerLedgerReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerLedgerReceiptAllocationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_multi_invoice_receipt_is_split_without_double_counting(): void
    {
        [$customer, $bank] = $this->context();
        $invoices = collect([
            $this->invoice($customer, 'SI-008', '240.00', '2026-07-13 09:00:00'),
            $this->invoice($customer, 'SI-007', '5000.00', '2026-07-13 09:01:00'),
            $this->invoice($customer, 'SI-004', '1980.00', '2026-07-13 09:02:00'),
        ]);
        $receipt = $this->receipt($customer, $bank, 'RV-010', '3240.00', '2026-07-13 10:00:00');

        foreach (['240.00', '2000.00', '1000.00'] as $index => $amount) {
            $receipt->allocations()->create(['sales_invoice_id' => $invoices[$index]->id, 'amount' => $amount]);
        }

        $detail = app(CustomerLedgerReportService::class)->detail($customer, '2026-07-13', '2026-07-13');
        $receiptRows = $detail['rows']->where('voucher_no', 'RV-010')->values();

        $this->assertSame(3, $receiptRows->count());
        $this->assertSame([240.0, 2000.0, 1000.0], $receiptRows->pluck('credit')->all());
        $this->assertSame(['SI-008', 'SI-007', 'SI-004'], $receiptRows->pluck('against_document')->all());
        $this->assertSame(3240.0, round((float) $receiptRows->sum('credit'), 2));
        $this->assertSame(3980.0, $detail['summary']['closing']);
        $this->assertFalse($receiptRows->contains(fn (array $row): bool => $row['particulars'] === 'Receipt RV-010'));
    }

    public function test_receipt_with_allocated_and_unallocated_amount_includes_residual_credit(): void
    {
        [$customer, $bank] = $this->context();
        $invoice = $this->invoice($customer, 'SI-001', '100.00', '2026-07-13 09:00:00');
        $receipt = $this->receipt($customer, $bank, 'RV-011', '125.55', '2026-07-13 10:00:00');
        $receipt->allocations()->create(['sales_invoice_id' => $invoice->id, 'amount' => '100.25']);

        $rows = app(CustomerLedgerReportService::class)->detail($customer, '2026-07-13', '2026-07-13')['rows']
            ->where('voucher_no', 'RV-011')
            ->values();

        $this->assertSame([100.25, 25.30], $rows->pluck('credit')->all());
        $this->assertStringContainsString('Unallocated customer credit', $rows->last()['particulars']);
    }

    public function test_legacy_receipt_without_allocations_keeps_single_header_row(): void
    {
        [$customer, $bank] = $this->context();
        $this->receipt($customer, $bank, 'RV-LEGACY', '75.00', '2026-07-13 10:00:00');

        $rows = app(CustomerLedgerReportService::class)->detail($customer, '2026-07-13', '2026-07-13')['rows'];

        $this->assertCount(1, $rows);
        $this->assertSame('Receipt RV-LEGACY', $rows->first()['particulars']);
        $this->assertSame(75.0, $rows->first()['credit']);
    }

    public function test_non_posted_receipt_and_its_allocations_are_excluded(): void
    {
        [$customer, $bank] = $this->context();
        $invoice = $this->invoice($customer, 'SI-001', '100.00', '2026-07-13 09:00:00');
        $receipt = $this->receipt($customer, $bank, 'RV-DRAFT', '50.00', '2026-07-13 10:00:00', 'draft');
        $receipt->allocations()->create(['sales_invoice_id' => $invoice->id, 'amount' => '50.00']);

        $rows = app(CustomerLedgerReportService::class)->detail($customer, '2026-07-13', '2026-07-13')['rows'];

        $this->assertFalse($rows->contains('voucher_no', 'RV-DRAFT'));
    }

    public function test_receipt_allocation_validation_allows_unallocated_credit_and_rejects_over_allocation(): void
    {
        [$customer] = $this->context();
        $invoice = $this->invoice($customer, 'SI-001', '100.00', '2026-07-13 09:00:00');

        ReceiptVoucherResource::validatePostableData([
            'customer_id' => $customer->id,
            'amount' => '125.00',
            'allocations' => [['sales_invoice_id' => $invoice->id, 'amount' => '100.00']],
        ]);

        $this->expectException(ValidationException::class);

        ReceiptVoucherResource::validatePostableData([
            'customer_id' => $customer->id,
            'amount' => '100.00',
            'allocations' => [['sales_invoice_id' => $invoice->id, 'amount' => '100.01']],
        ]);
    }

    public function test_edited_and_deleted_allocations_are_reflected_without_stale_report_rows(): void
    {
        [$customer, $bank] = $this->context();
        $invoice = $this->invoice($customer, 'SI-001', '100.00', '2026-07-13 09:00:00');
        $receipt = $this->receipt($customer, $bank, 'RV-EDIT', '60.00', '2026-07-13 10:00:00');
        $allocation = $receipt->allocations()->create(['sales_invoice_id' => $invoice->id, 'amount' => '40.00']);

        $allocation->update(['amount' => '60.00']);
        $rows = app(CustomerLedgerReportService::class)->detail($customer, '2026-07-13', '2026-07-13')['rows'];

        $this->assertSame([60.0], $rows->where('voucher_no', 'RV-EDIT')->pluck('credit')->all());

        $invoice->update(['status' => InvoiceStatus::Paid]);
        $receipt->delete();
        $rows = app(CustomerLedgerReportService::class)->detail($customer, '2026-07-13', '2026-07-13')['rows'];

        $this->assertFalse($rows->contains('voucher_no', 'RV-EDIT'));
        $this->assertDatabaseMissing('voucher_allocations', ['id' => $allocation->id]);
        $this->assertSame(InvoiceStatus::Posted, $invoice->refresh()->status);
    }

    private function context(): array
    {
        $company = Company::factory()->create();
        $this->actingAs(User::factory()->create(['company_id' => $company->id]));
        $customer = Customer::factory()->create(['company_id' => $company->id, 'opening_balance' => 0]);
        $bank = BankAccount::query()->create([
            'company_id' => $company->id,
            'bank_name' => 'Test Bank',
            'account_name' => 'Current',
            'opening_balance' => 0,
            'status' => 'active',
        ]);

        return [$customer, $bank];
    }

    private function invoice(Customer $customer, string $number, string $total, string $createdAt): SalesInvoice
    {
        $invoice = SalesInvoice::query()->create([
            'company_id' => $customer->company_id,
            'customer_id' => $customer->id,
            'invoice_no' => $number,
            'invoice_date' => '2026-07-13',
            'subtotal' => $total,
            'discount' => 0,
            'vat_total' => 0,
            'total' => $total,
            'status' => InvoiceStatus::Posted,
        ]);
        $invoice->forceFill(['created_at' => Carbon::parse($createdAt), 'updated_at' => Carbon::parse($createdAt)])->saveQuietly();

        return $invoice;
    }

    private function receipt(Customer $customer, BankAccount $bank, string $number, string $amount, string $createdAt, string $status = 'posted'): Voucher
    {
        $voucher = Voucher::query()->create([
            'company_id' => $customer->company_id,
            'bank_account_id' => $bank->id,
            'voucher_type' => 'receipt',
            'receipt_voucher_type' => 'customer',
            'voucher_no' => $number,
            'voucher_date' => '2026-07-13',
            'customer_id' => $customer->id,
            'amount' => $amount,
            'status' => $status,
        ]);
        $voucher->forceFill(['created_at' => Carbon::parse($createdAt), 'updated_at' => Carbon::parse($createdAt)])->saveQuietly();

        return $voucher;
    }
}
