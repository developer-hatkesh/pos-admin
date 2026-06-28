<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\ExpenseStatus;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\Expense;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExpensePaymentService
{
    public function __construct(
        private readonly ExpensePostingService $expensePosting,
        private readonly VoucherPostingService $voucherPosting,
    ) {}

    public function pay(Expense $expense, int $bankAccountId, mixed $paymentDate): Expense
    {
        if ($expense->payment_voucher_id !== null) {
            return $expense->refresh();
        }

        if ($expense->supplier_id === null) {
            throw new RuntimeException('Supplier is required when expense status is paid.');
        }

        if ($bankAccountId < 1 || blank($paymentDate)) {
            throw new RuntimeException('Bank account and payment date are required when expense status is paid.');
        }

        return DB::transaction(function () use ($expense, $bankAccountId, $paymentDate): Expense {
            $expense->refresh();

            if ($expense->journal_id === null) {
                $expense = $this->expensePosting->post($expense);
            }

            $voucher = Voucher::withoutGlobalScopes()->create([
                'company_id' => $expense->company_id,
                'voucher_type' => VoucherType::Payment,
                'payment_voucher_type' => 'expense',
                'voucher_date' => $paymentDate,
                'bank_account_id' => $bankAccountId,
                'supplier_id' => $expense->supplier_id,
                'amount' => $expense->grand_total_amount,
                'reference_no' => $expense->voucher_no,
                'notes' => 'Payment for expense '.$expense->voucher_no,
                'status' => VoucherStatus::Draft,
                'created_by' => auth()->id(),
            ]);

            $voucher->allocations()->create([
                'expense_id' => $expense->id,
                'amount' => $expense->grand_total_amount,
            ]);

            $this->voucherPosting->post($voucher);

            $expense->forceFill([
                'status' => ExpenseStatus::Paid,
                'payment_bank_account_id' => $bankAccountId,
                'payment_date' => $paymentDate,
                'payment_voucher_id' => $voucher->id,
            ])->save();

            return $expense->refresh();
        });
    }
}
