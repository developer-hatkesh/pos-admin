<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\CustomerLedgerReports\CustomerLedgerReportResource;
use App\Filament\Resources\ReceiptVouchers\ReceiptVoucherResource;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\JournalVoucher;
use App\Models\Ledger;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Accounting\CustomerCreditReconciliationService;
use App\Services\Accounting\VoucherPostingService;
use App\Services\Reports\CustomerLedgerReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerLedgerReceiptAllocationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_allocation_does_not_inflate_actual_receipt_amount(): void
    {
        $data = ReceiptVoucherResource::calculateTotalsFromData([
            'receipt_voucher_type' => 'customer',
            'amount' => '18000.00',
            'allocations' => [['sales_invoice_id' => 999, 'amount' => '7000.00']],
        ], true);

        $this->assertSame(7000.0, $data['amount']);
        $this->assertSame(7000.0, $data['allocations'][0]['amount']);
    }

    public function test_correcting_posted_receipt_synchronizes_bank_and_journal_amounts(): void
    {
        [$customer, $bank] = $this->context();
        $bankLedger = Ledger::query()->create([
            'company_id' => $customer->company_id,
            'name' => 'Test Bank Ledger',
            'nominal_code' => '1200',
            'type' => 'asset',
            'opening_balance' => 0,
            'balance_type' => 'Dr',
            'status' => 'active',
        ]);
        $customerLedger = Ledger::query()->create([
            'company_id' => $customer->company_id,
            'name' => 'JP LTD Receivable',
            'nominal_code' => 'CUST-TEST',
            'type' => 'asset',
            'opening_balance' => 0,
            'balance_type' => 'Dr',
            'status' => 'active',
        ]);
        $bank->update(['ledger_id' => $bankLedger->id]);
        $customer->update(['ledger_id' => $customerLedger->id, 'chart_account_id' => $customerLedger->id]);
        $invoice = $this->invoice($customer, 'SI-PARTIAL', '18000.00', '2026-07-13 09:00:00');
        $voucher = $this->receipt($customer, $bank, 'RV-PARTIAL', '18000.00', '2026-07-13 10:00:00', 'draft');
        $voucher->allocations()->create(['sales_invoice_id' => $invoice->id, 'amount' => '7000.00']);

        app(VoucherPostingService::class)->post($voucher);
        $voucher->update(['amount' => '7000.00']);
        app(VoucherPostingService::class)->synchronizePosted($voucher->refresh());

        $voucher->refresh()->load('bankTransaction.journalEntry.journalLines');
        $this->assertSame('7000.00', $voucher->amount);
        $this->assertSame('7000.00', $voucher->bankTransaction->amount);
        $this->assertSame(7000.0, (float) $voucher->bankTransaction->journalEntry->journalLines->sum('debit'));
        $this->assertSame(7000.0, (float) $voucher->bankTransaction->journalEntry->journalLines->sum('credit'));
        $this->assertSame(InvoiceStatus::Partial, $invoice->refresh()->status);
    }

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
        $this->assertStringContainsString('Unallocated customer credit', $rows->first()['particulars']);
        $this->assertSame(75.0, $rows->first()['credit']);
    }

    public function test_summary_separates_invoice_outstanding_from_unallocated_credit(): void
    {
        [$customer, $bank] = $this->context();
        $this->invoice($customer, 'SI-001', '100.00', '2026-07-13 09:00:00');
        $this->receipt($customer, $bank, 'RV-CREDIT', '70.00', '2026-07-13 10:00:00');

        $summary = app(CustomerLedgerReportService::class)->summary($customer);

        $this->assertSame(100.0, $summary['invoice_outstanding']);
        $this->assertSame(70.0, $summary['unallocated_credit']);
        $this->assertSame(30.0, $summary['closing']);
    }

    public function test_customer_balance_filter_uses_each_customer_balance(): void
    {
        [$debitCustomer, $bank] = $this->context();
        $creditCustomer = Customer::factory()->create([
            'company_id' => $debitCustomer->company_id,
            'opening_balance' => 0,
        ]);
        $this->invoice($debitCustomer, 'SI-DEBIT', '100.00', '2026-07-13 09:00:00');
        $this->receipt($creditCustomer, $bank, 'RV-CREDIT', '70.00', '2026-07-13 10:00:00');

        $filters = new class
        {
            public string $status = 'all';

            public string $balanceType = 'debit';

            public function reportStartDate(): ?string
            {
                return null;
            }

            public function reportEndDate(): ?string
            {
                return null;
            }
        };

        $debitIds = CustomerLedgerReportResource::applyPermanentFilters(Customer::query(), $filters)->pluck('id');
        $this->assertTrue($debitIds->contains($debitCustomer->id));
        $this->assertFalse($debitIds->contains($creditCustomer->id));

        $filters->balanceType = 'credit';
        $creditIds = CustomerLedgerReportResource::applyPermanentFilters(Customer::query(), $filters)->pluck('id');
        $this->assertTrue($creditIds->contains($creditCustomer->id));
        $this->assertFalse($creditIds->contains($debitCustomer->id));
    }

    public function test_unallocated_receipt_can_be_reconciled_without_new_journal(): void
    {
        [$customer, $bank] = $this->context();
        $invoice = $this->invoice($customer, 'SI-001', '100.00', '2026-07-13 09:00:00');
        $receipt = $this->receipt($customer, $bank, 'RV-CREDIT', '70.00', '2026-07-13 10:00:00');

        $result = app(CustomerCreditReconciliationService::class)->reconcile($customer);

        $this->assertSame(70.0, $result['total_allocated']);
        $this->assertSame(1, $result['invoice_count']);
        $this->assertDatabaseHas('voucher_allocations', [
            'voucher_id' => $receipt->id,
            'sales_invoice_id' => $invoice->id,
            'amount' => '70.00',
        ]);
        $this->assertNull($receipt->refresh()->journal_id);
        $this->assertSame(InvoiceStatus::Partial, $invoice->refresh()->status);

        $summary = app(CustomerLedgerReportService::class)->summary($customer);
        $this->assertSame(30.0, $summary['invoice_outstanding']);
        $this->assertSame(0.0, $summary['unallocated_credit']);
        $this->assertSame(30.0, $summary['closing']);
    }

    public function test_existing_partial_credit_note_allocation_is_extended_to_settle_invoice(): void
    {
        [$customer] = $this->context();
        $invoice = $this->invoice($customer, 'SI-001', '100.00', '2026-07-13 09:00:00');
        $journal = JournalEntry::query()->create([
            'company_id' => $customer->company_id,
            'entry_date' => '2026-07-13',
            'source_type' => 'sales_return',
            'description' => 'Test credit note journal',
        ]);
        $return = SalesReturn::query()->create([
            'company_id' => $customer->company_id,
            'return_no' => 'CN-001',
            'sales_invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'return_date' => '2026-07-13',
            'subtotal' => '100.00',
            'vat_total' => 0,
            'total' => '100.00',
            'status' => 'posted',
            'journal_id' => $journal->id,
        ]);
        $journalVoucher = JournalVoucher::query()->create([
            'company_id' => $customer->company_id,
            'voucher_date' => '2026-07-13',
            'form_type' => 'credit_note',
            'sales_return_id' => $return->id,
            'narration' => 'Test credit allocation',
        ]);
        $allocation = $journalVoucher->allocations()->create([
            'sales_invoice_id' => $invoice->id,
            'amount' => '70.00',
        ]);
        $invoice->update(['status' => InvoiceStatus::Partial]);

        $before = app(CustomerLedgerReportService::class)->summary($customer);
        $this->assertSame(30.0, $before['invoice_outstanding']);
        $this->assertSame(30.0, $before['unallocated_credit']);
        $this->assertSame(0.0, $before['closing']);

        $result = app(CustomerCreditReconciliationService::class)->reconcile($customer);

        $this->assertSame(30.0, $result['credit_note_allocated']);
        $this->assertSame('100.00', $allocation->refresh()->amount);
        $this->assertSame(InvoiceStatus::Paid, $invoice->refresh()->status);

        $after = app(CustomerLedgerReportService::class)->summary($customer);
        $this->assertSame(0.0, $after['invoice_outstanding']);
        $this->assertSame(0.0, $after['unallocated_credit']);
        $this->assertSame(0.0, $after['closing']);
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
