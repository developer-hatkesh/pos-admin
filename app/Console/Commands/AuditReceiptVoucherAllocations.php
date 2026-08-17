<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\Voucher;
use Illuminate\Console\Command;

class AuditReceiptVoucherAllocations extends Command
{
    protected $signature = 'receipt-vouchers:audit {--company= : Limit the audit to one company ID}';

    protected $description = 'Dry-run audit of posted receipt amounts, allocations, bank transactions, and journals';

    public function handle(): int
    {
        $rows = Voucher::withoutGlobalScopes()
            ->where('voucher_type', VoucherType::Receipt->value)
            ->where('status', VoucherStatus::Posted->value)
            ->when($this->option('company'), fn ($query, $companyId) => $query->where('company_id', (int) $companyId))
            ->with(['customer', 'allocations.salesInvoice', 'bankTransaction.journalEntry.journalLines'])
            ->orderBy('company_id')
            ->orderBy('id')
            ->get()
            ->map(function (Voucher $voucher): array {
                $allocated = round((float) $voucher->allocations->sum('amount'), 2);
                $unallocated = round(max(0, (float) $voucher->amount - $allocated), 2);
                $bankAmount = $voucher->bankTransaction ? round((float) $voucher->bankTransaction->amount, 2) : null;
                $journalDebit = $voucher->bankTransaction?->journalEntry
                    ? round((float) $voucher->bankTransaction->journalEntry->journalLines->sum('debit'), 2)
                    : null;
                $journalCredit = $voucher->bankTransaction?->journalEntry
                    ? round((float) $voucher->bankTransaction->journalEntry->journalLines->sum('credit'), 2)
                    : null;
                $consistent = $bankAmount === round((float) $voucher->amount, 2)
                    && $journalDebit === round((float) $voucher->amount, 2)
                    && $journalCredit === round((float) $voucher->amount, 2);

                return [
                    $voucher->id,
                    $voucher->company_id,
                    $voucher->voucher_no,
                    $voucher->customer?->name ?? '-',
                    number_format((float) $voucher->amount, 2, '.', ''),
                    number_format($allocated, 2, '.', ''),
                    number_format($unallocated, 2, '.', ''),
                    $bankAmount === null ? 'missing' : number_format($bankAmount, 2, '.', ''),
                    $journalDebit === null ? 'missing' : number_format($journalDebit, 2, '.', ''),
                    $journalCredit === null ? 'missing' : number_format($journalCredit, 2, '.', ''),
                    $voucher->bankTransaction?->reconciled ? 'yes' : 'no',
                    $consistent ? ($unallocated > 0 ? 'review credit' : 'consistent') : 'mismatch',
                ];
            });

        $this->table(
            ['ID', 'Company', 'Voucher', 'Customer', 'Receipt', 'Allocated', 'Unallocated', 'Bank', 'Journal Dr', 'Journal Cr', 'Reconciled', 'Result'],
            $rows,
        );
        $this->info('Dry run only: no records were changed.');

        return self::SUCCESS;
    }
}
