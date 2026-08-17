<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\Voucher;
use App\Services\Accounting\VoucherPostingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CorrectReceiptVoucher extends Command
{
    protected $signature = 'receipt-vouchers:correct
        {voucher : Receipt voucher number, for example RV-003}
        {--amount= : Correct actual receipt amount}
        {--company= : Company ID}
        {--force : Apply without interactive confirmation}';

    protected $description = 'Correct an unreconciled posted receipt and synchronize its bank transaction and journal';

    public function handle(VoucherPostingService $posting): int
    {
        if (! is_numeric($this->option('amount')) || round((float) $this->option('amount'), 2) <= 0) {
            $this->error('A positive --amount is required.');

            return self::FAILURE;
        }

        if (! is_numeric($this->option('company')) || (int) $this->option('company') < 1) {
            $this->error('A valid --company ID is required.');

            return self::FAILURE;
        }

        $voucher = Voucher::withoutGlobalScopes()
            ->where('company_id', (int) $this->option('company'))
            ->where('voucher_type', VoucherType::Receipt->value)
            ->where('voucher_no', (string) $this->argument('voucher'))
            ->with(['allocations', 'bankTransaction'])
            ->first();

        if (! $voucher) {
            $this->error('Receipt voucher was not found for the specified company.');

            return self::FAILURE;
        }

        if ($voucher->status !== VoucherStatus::Posted || $voucher->bank_transaction_id === null) {
            $this->error('Only a posted receipt with a linked bank transaction can be corrected.');

            return self::FAILURE;
        }

        if ($voucher->bankTransaction?->reconciled) {
            $this->error('This bank transaction is reconciled. Reverse the receipt instead of amending it.');

            return self::FAILURE;
        }

        $amount = round((float) $this->option('amount'), 2);
        $allocated = round((float) $voucher->allocations->sum('amount'), 2);

        if ($allocated > $amount) {
            $this->error('The corrected receipt amount cannot be less than its allocated amount of '.number_format($allocated, 2, '.', '').'.');

            return self::FAILURE;
        }

        $this->table(['Voucher', 'Old receipt', 'New receipt', 'Allocated', 'Unallocated after correction'], [[
            $voucher->voucher_no,
            number_format((float) $voucher->amount, 2, '.', ''),
            number_format($amount, 2, '.', ''),
            number_format($allocated, 2, '.', ''),
            number_format($amount - $allocated, 2, '.', ''),
        ]]);

        if (! $this->option('force') && ! $this->confirm('Apply this correction to the voucher, bank transaction, and journal?')) {
            $this->warn('Correction cancelled; no records were changed.');

            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($voucher, $amount, $posting): void {
                $lockedVoucher = Voucher::withoutGlobalScopes()->lockForUpdate()->findOrFail($voucher->id);
                $lockedVoucher->update(['amount' => number_format($amount, 2, '.', '')]);
                $posting->synchronizePosted($lockedVoucher->refresh());
            });
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->implode(' '));

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("{$voucher->voucher_no} was corrected and its accounting records were synchronized.");

        return self::SUCCESS;
    }
}
