<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Customer;
use App\Models\JournalVoucher;
use App\Models\Ledger;
use App\Models\Supplier;
use App\Services\Accounting\JournalVoucherService;
use App\Services\Reports\CustomerLedgerReportService;
use App\Services\Reports\SupplierLedgerReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualJournalAccountTargetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_bank_transfer_posts_to_both_ledgers_and_bank_activity(): void
    {
        $company = Company::factory()->create();
        $sourceLedger = $this->ledger($company, 'BANK-1', 'Bank 1');
        $destinationLedger = $this->ledger($company, 'BANK-2', 'Bank 2');
        $source = $this->bank($company, $sourceLedger, 'Bank 1');
        $destination = $this->bank($company, $destinationLedger, 'Bank 2');
        $voucher = $this->voucher($company, 'JV-BANK');

        app(JournalVoucherService::class)->completeManual($voucher, [
            ['account_target' => 'bank:'.$destination->id, 'debit' => 250, 'credit' => 0, 'particulars' => 'Transfer received'],
            ['account_target' => 'bank:'.$source->id, 'debit' => 0, 'credit' => 250, 'particulars' => 'Transfer sent'],
        ]);

        $this->assertDatabaseHas('journal_lines', ['journal_id' => $voucher->refresh()->journal_id, 'ledger_id' => $destinationLedger->id, 'bank_account_id' => $destination->id, 'debit' => '250.00']);
        $this->assertDatabaseHas('journal_lines', ['journal_id' => $voucher->journal_id, 'ledger_id' => $sourceLedger->id, 'bank_account_id' => $source->id, 'credit' => '250.00']);
        $this->assertDatabaseHas('bank_transactions', ['bank_account_id' => $destination->id, 'type' => 'deposit', 'amount' => '250.00', 'journal_id' => $voucher->journal_id]);
        $this->assertDatabaseHas('bank_transactions', ['bank_account_id' => $source->id, 'type' => 'withdrawal', 'amount' => '250.00', 'journal_id' => $voucher->journal_id]);
        $this->assertSame(250.0, $destination->currentBalance());
        $this->assertSame(-250.0, $source->currentBalance());
    }

    public function test_customer_and_supplier_targets_appear_in_their_subledgers(): void
    {
        $company = Company::factory()->create();
        $receivable = $this->ledger($company, '1200', 'Trade Debtors');
        $payable = $this->ledger($company, '2100', 'Trade Creditors', 'liability');
        $customer = Customer::factory()->create(['company_id' => $company->id, 'ledger_id' => $receivable->id, 'chart_account_id' => $receivable->id]);
        $supplier = Supplier::factory()->create(['company_id' => $company->id, 'ledger_id' => $payable->id, 'chart_account_id' => $payable->id]);
        $voucher = $this->voucher($company, 'JV-PARTY');

        app(JournalVoucherService::class)->completeManual($voucher, [
            ['account_target' => 'customer:'.$customer->id, 'debit' => 80, 'credit' => 0, 'particulars' => 'Customer adjustment'],
            ['account_target' => 'supplier:'.$supplier->id, 'debit' => 0, 'credit' => 80, 'particulars' => 'Supplier adjustment'],
        ]);

        $customerRows = app(CustomerLedgerReportService::class)->detail($customer)['rows'];
        $supplierRows = app(SupplierLedgerReportService::class)->detail($supplier)['rows'];

        $this->assertSame(80.0, $customerRows->firstWhere('voucher_no', 'JV-PARTY')['debit']);
        $this->assertSame(80.0, $supplierRows->firstWhere('voucher_no', 'JV-PARTY')['credit']);
    }

    public function test_existing_numeric_ledger_input_remains_supported(): void
    {
        $company = Company::factory()->create();
        $debit = $this->ledger($company, '5000', 'Expense', 'expense');
        $credit = $this->ledger($company, '4000', 'Income', 'income');
        $voucher = $this->voucher($company, 'JV-LEGACY');

        app(JournalVoucherService::class)->completeManual($voucher, [
            ['ledger_id' => $debit->id, 'debit' => 10, 'credit' => 0],
            ['ledger_id' => $credit->id, 'debit' => 0, 'credit' => 10],
        ]);

        $this->assertDatabaseCount('journal_lines', 2);
        $this->assertNotNull($voucher->refresh()->journal_id);
    }

    private function ledger(Company $company, string $code, string $name, string $type = 'asset'): Ledger
    {
        return Ledger::query()->create([
            'company_id' => $company->id,
            'nominal_code' => $code,
            'name' => $name,
            'type' => $type,
            'opening_balance' => 0,
            'balance_type' => in_array($type, ['liability', 'income'], true) ? 'Cr' : 'Dr',
            'status' => 'active',
        ]);
    }

    private function bank(Company $company, Ledger $ledger, string $name): BankAccount
    {
        return BankAccount::query()->create([
            'company_id' => $company->id,
            'ledger_id' => $ledger->id,
            'bank_name' => $name,
            'account_name' => 'Current Account',
            'opening_balance' => 0,
            'status' => 'active',
        ]);
    }

    private function voucher(Company $company, string $number): JournalVoucher
    {
        return JournalVoucher::query()->create([
            'company_id' => $company->id,
            'voucher_no' => $number,
            'voucher_date' => '2026-10-08',
            'form_type' => 'manual',
            'narration' => 'Manual journal test',
        ]);
    }
}
