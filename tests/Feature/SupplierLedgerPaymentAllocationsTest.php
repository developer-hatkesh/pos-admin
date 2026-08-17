<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\PaymentVouchers\PaymentVoucherResource;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Reports\SupplierLedgerReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SupplierLedgerPaymentAllocationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_amount_is_derived_from_all_pay_amount_rows(): void
    {
        $data = PaymentVoucherResource::calculateTotalsFromData([
            'payment_voucher_type' => 'purchase',
            'amount' => '9999.00',
            'allocations' => [
                ['purchase_invoice_id' => 999, 'amount' => '1000.00'],
                ['purchase_invoice_id' => 998, 'amount' => '250.50'],
            ],
        ], true);

        $this->assertSame(1250.50, $data['amount']);
        $this->assertSame([1000.0, 250.5], collect($data['allocations'])->pluck('amount')->all());
    }

    public function test_multi_purchase_payment_is_split_without_double_counting(): void
    {
        [$supplier, $bank] = $this->context();
        $invoices = collect([
            $this->invoice($supplier, 'PI-003', '240.00', '2026-07-13 09:00:00'),
            $this->invoice($supplier, 'PI-002', '5000.00', '2026-07-13 09:01:00'),
            $this->invoice($supplier, 'PI-001', '1980.00', '2026-07-13 09:02:00'),
        ]);
        $payment = $this->payment($supplier, $bank, 'PV-010', '3240.00', '2026-07-13 10:00:00');

        foreach (['240.00', '2000.00', '1000.00'] as $index => $amount) {
            $payment->allocations()->create(['purchase_invoice_id' => $invoices[$index]->id, 'amount' => $amount]);
        }

        $detail = app(SupplierLedgerReportService::class)->detail($supplier, '2026-07-13', '2026-07-13');
        $paymentRows = $detail['rows']->where('voucher_no', 'PV-010')->values();

        $this->assertSame(3, $paymentRows->count());
        $this->assertSame([240.0, 2000.0, 1000.0], $paymentRows->pluck('debit')->all());
        $this->assertSame(['PI-003', 'PI-002', 'PI-001'], $paymentRows->pluck('against_document')->all());
        $this->assertSame(3240.0, round((float) $paymentRows->sum('debit'), 2));
        $this->assertSame(-3980.0, $detail['summary']['closing']);
        $this->assertFalse($paymentRows->contains(fn (array $row): bool => $row['particulars'] === 'Payment PV-010'));
    }

    public function test_unallocated_and_legacy_supplier_payments_are_not_omitted(): void
    {
        [$supplier, $bank] = $this->context();
        $invoice = $this->invoice($supplier, 'PI-001', '100.00', '2026-07-13 09:00:00');
        $mixed = $this->payment($supplier, $bank, 'PV-MIXED', '125.55', '2026-07-13 10:00:00');
        $mixed->allocations()->create(['purchase_invoice_id' => $invoice->id, 'amount' => '100.25']);
        $this->payment($supplier, $bank, 'PV-LEGACY', '75.00', '2026-07-13 11:00:00');

        $rows = app(SupplierLedgerReportService::class)->detail($supplier, '2026-07-13', '2026-07-13')['rows'];
        $mixedRows = $rows->where('voucher_no', 'PV-MIXED')->values();

        $this->assertSame([100.25, 25.30], $mixedRows->pluck('debit')->all());
        $this->assertStringContainsString('Unallocated supplier payment', $mixedRows->last()['particulars']);
        $this->assertSame('Payment PV-LEGACY', $rows->firstWhere('voucher_no', 'PV-LEGACY')['particulars']);
    }

    private function context(): array
    {
        $company = Company::factory()->create();
        $this->actingAs(User::factory()->create(['company_id' => $company->id]));
        $supplier = Supplier::factory()->create(['company_id' => $company->id, 'opening_balance' => 0]);
        $bank = BankAccount::query()->create([
            'company_id' => $company->id,
            'bank_name' => 'Test Bank',
            'account_name' => 'Current',
            'opening_balance' => 0,
            'status' => 'active',
        ]);

        return [$supplier, $bank];
    }

    private function invoice(Supplier $supplier, string $number, string $total, string $createdAt): PurchaseInvoice
    {
        $invoice = PurchaseInvoice::query()->create([
            'company_id' => $supplier->company_id,
            'supplier_id' => $supplier->id,
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

    private function payment(Supplier $supplier, BankAccount $bank, string $number, string $amount, string $createdAt): Voucher
    {
        $voucher = Voucher::query()->create([
            'company_id' => $supplier->company_id,
            'bank_account_id' => $bank->id,
            'voucher_type' => 'payment',
            'payment_voucher_type' => 'purchase',
            'voucher_no' => $number,
            'voucher_date' => '2026-07-13',
            'supplier_id' => $supplier->id,
            'amount' => $amount,
            'status' => 'posted',
        ]);
        $voucher->forceFill(['created_at' => Carbon::parse($createdAt), 'updated_at' => Carbon::parse($createdAt)])->saveQuietly();

        return $voucher;
    }
}
