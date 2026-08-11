<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Reports\BankLedgerReportService;
use App\Services\Reports\CustomerLedgerReportService;
use App\Services\Reports\SupplierLedgerReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LedgerTransactionOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_ledger_orders_same_date_rows_by_creation_time(): void
    {
        [$company, $bank] = $this->context();
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        $payment = $this->voucher($company, $bank, 'PV-001', 'payment', '2026-07-01 09:00:00', customerId: $customer->id, subtype: 'credit_note');
        $receipt = $this->voucher($company, $bank, 'RV-001', 'receipt', '2026-07-01 10:00:00', customerId: $customer->id);

        $rows = app(CustomerLedgerReportService::class)->detail($customer, '2026-07-01', '2026-07-01')['rows'];

        $this->assertSame([$payment->voucher_no, $receipt->voucher_no], $rows->pluck('voucher_no')->all());
        $this->assertSame([25.0, 0.0], $rows->pluck('balance')->all());
    }

    public function test_supplier_ledger_orders_same_date_rows_by_creation_time(): void
    {
        [$company, $bank] = $this->context();
        $supplier = Supplier::factory()->create(['company_id' => $company->id]);

        $receipt = $this->voucher($company, $bank, 'RV-002', 'receipt', '2026-07-01 09:00:00', supplierId: $supplier->id, receiptSubtype: 'purchase_return');
        $payment = $this->voucher($company, $bank, 'PV-002', 'payment', '2026-07-01 10:00:00', supplierId: $supplier->id);

        $rows = app(SupplierLedgerReportService::class)->detail($supplier, '2026-07-01', '2026-07-01')['rows'];

        $this->assertSame([$receipt->voucher_no, $payment->voucher_no], $rows->pluck('voucher_no')->all());
    }

    public function test_bank_ledger_orders_by_date_creation_time_and_id_before_calculating_balance(): void
    {
        [$company, $bank] = $this->context();

        $later = $this->bankTransaction($company, $bank, 'Later', 'withdrawal', 25, '2026-07-01 10:00:00');
        $earlier = $this->bankTransaction($company, $bank, 'Earlier', 'deposit', 100, '2026-07-01 09:00:00');

        $rows = app(BankLedgerReportService::class)->detail($bank, '2026-07-01', '2026-07-01')['rows'];

        $this->assertSame([$earlier->reference, $later->reference], $rows->pluck('voucher_no')->all());
        $this->assertSame([100.0, 0.0], $rows->pluck('debit')->all());
        $this->assertSame([0.0, 25.0], $rows->pluck('credit')->all());
        $this->assertSame([100.0, 75.0], $rows->pluck('balance')->all());
    }

    private function context(): array
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $this->actingAs($user);
        $bank = BankAccount::query()->create(['company_id' => $company->id, 'bank_name' => 'Test Bank', 'account_name' => 'Current', 'opening_balance' => 0, 'status' => 'active']);

        return [$company, $bank];
    }

    private function voucher(Company $company, BankAccount $bank, string $number, string $type, string $createdAt, ?int $customerId = null, ?int $supplierId = null, string $subtype = 'purchase', string $receiptSubtype = 'customer'): Voucher
    {
        $voucher = Voucher::query()->create(['company_id' => $company->id, 'bank_account_id' => $bank->id, 'voucher_type' => $type, 'payment_voucher_type' => $subtype, 'receipt_voucher_type' => $receiptSubtype, 'voucher_no' => $number, 'voucher_date' => '2026-07-01', 'customer_id' => $customerId, 'supplier_id' => $supplierId, 'amount' => 25, 'status' => 'posted']);
        $voucher->forceFill(['created_at' => Carbon::parse($createdAt), 'updated_at' => Carbon::parse($createdAt)])->saveQuietly();

        return $voucher;
    }

    private function bankTransaction(Company $company, BankAccount $bank, string $reference, string $type, float $amount, string $createdAt): BankTransaction
    {
        $transaction = BankTransaction::query()->create(['company_id' => $company->id, 'bank_account_id' => $bank->id, 'transaction_date' => '2026-07-01', 'type' => $type, 'amount' => $amount, 'reference' => $reference]);
        $transaction->forceFill(['created_at' => Carbon::parse($createdAt), 'updated_at' => Carbon::parse($createdAt)])->saveQuietly();

        return $transaction;
    }
}
