<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\BalanceType;
use App\Models\JournalLine;
use App\Models\Ledger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LedgerBalanceService
{
    public function openingBalance(Ledger $ledger, ?string $fromDate = null): float
    {
        $opening = $this->signedOpeningBalance($ledger);

        if (blank($fromDate)) {
            return round($opening, 2);
        }

        $movement = $this->signedMovement($ledger, fn (Builder $query): Builder => $query
            ->whereHas('journalEntry', fn (Builder $journal): Builder => $journal
                ->where('company_id', $ledger->company_id)
                ->whereDate('entry_date', '<', $fromDate)));

        return round($opening + $movement, 2);
    }

    public function periodTotals(Ledger $ledger, ?string $fromDate = null, ?string $toDate = null): array
    {
        $row = JournalLine::query()
            ->where('ledger_id', $ledger->id)
            ->whereHas('journalEntry', function (Builder $query) use ($ledger, $fromDate, $toDate): Builder {
                $query->where('company_id', $ledger->company_id);

                if (filled($fromDate)) {
                    $query->whereDate('entry_date', '>=', $fromDate);
                }

                if (filled($toDate)) {
                    $query->whereDate('entry_date', '<=', $toDate);
                }

                return $query;
            })
            ->selectRaw('COALESCE(SUM(debit), 0) as debit_total, COALESCE(SUM(credit), 0) as credit_total, MAX(journal_lines.id) as max_id')
            ->first();

        return [
            'debit' => round((float) ($row?->debit_total ?? 0), 2),
            'credit' => round((float) ($row?->credit_total ?? 0), 2),
        ];
    }

    public function closingBalance(Ledger $ledger, ?string $fromDate = null, ?string $toDate = null): float
    {
        $opening = $this->openingBalance($ledger, $fromDate);
        $totals = $this->periodTotals($ledger, $fromDate, $toDate);

        return round($opening + $this->signedAmount($ledger, $totals['debit'], $totals['credit']), 2);
    }

    public function formattedBalance(float $signedBalance): string
    {
        if (round($signedBalance, 2) === 0.0) {
            return CurrencyService::format(0);
        }

        return CurrencyService::format(abs($signedBalance)).' '.($signedBalance > 0 ? BalanceType::Debit->value : BalanceType::Credit->value);
    }

    public function balanceType(float $signedBalance): string
    {
        if (round($signedBalance, 2) === 0.0) {
            return '';
        }

        return $signedBalance > 0 ? BalanceType::Debit->value : BalanceType::Credit->value;
    }

    public function runningRows(Ledger $ledger, ?string $fromDate = null, ?string $toDate = null): Collection
    {
        $running = $this->openingBalance($ledger, $fromDate);

        return JournalLine::query()
            ->with('journalEntry')
            ->where('ledger_id', $ledger->id)
            ->whereHas('journalEntry', function (Builder $query) use ($ledger, $fromDate, $toDate): Builder {
                $query->where('company_id', $ledger->company_id);

                if (filled($fromDate)) {
                    $query->whereDate('entry_date', '>=', $fromDate);
                }

                if (filled($toDate)) {
                    $query->whereDate('entry_date', '<=', $toDate);
                }

                return $query;
            })
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_id')
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_lines.id')
            ->select('journal_lines.*')
            ->get()
            ->map(function (JournalLine $line) use ($ledger, &$running): array {
                $running = round($running + $this->signedAmount($ledger, (float) $line->debit, (float) $line->credit), 2);

                return [
                    'date' => $line->journalEntry?->entry_date,
                    'voucher_no' => $line->journalEntry?->reference,
                    'voucher_type' => $this->voucherType($line->journalEntry?->source_type?->value),
                    'particulars' => $line->description ?: $line->journalEntry?->description,
                    'debit' => (float) $line->debit,
                    'credit' => (float) $line->credit,
                    'balance' => $running,
                    'dr_cr' => $this->balanceType($running),
                ];
            });
    }

    public function signedAmount(Ledger $ledger, float $debit, float $credit): float
    {
        return $debit - $credit;
    }

    private function signedMovement(Ledger $ledger, callable $scope): float
    {
        $row = $scope(JournalLine::query()->where('ledger_id', $ledger->id))
            ->selectRaw('COALESCE(SUM(debit), 0) as debit_total, COALESCE(SUM(credit), 0) as credit_total')
            ->first();

        return $this->signedAmount($ledger, (float) ($row?->debit_total ?? 0), (float) ($row?->credit_total ?? 0));
    }

    private function signedOpeningBalance(Ledger $ledger): float
    {
        $amount = (float) $ledger->opening_balance;
        $type = $ledger->balance_type;

        if ($type instanceof BalanceType) {
            return $type === BalanceType::Credit ? -abs($amount) : abs($amount);
        }

        return $this->normalBalanceType($ledger) === BalanceType::Credit ? -abs($amount) : abs($amount);
    }

    private function normalBalanceType(Ledger $ledger): BalanceType
    {
        return in_array($ledger->type?->value, ['liability', 'income', 'equity'], true)
            ? BalanceType::Credit
            : BalanceType::Debit;
    }

    private function voucherType(?string $sourceType): string
    {
        return match ($sourceType) {
            'sales' => 'Sales Invoice',
            'sales_return' => 'Sales Return',
            'purchase' => 'Purchase Invoice',
            'purchase_return' => 'Purchase Return',
            'payment' => 'Payment',
            'voucher' => 'Voucher',
            'bank' => 'Receipt / Payment',
            'opening_balance' => 'Opening Balance',
            'manual' => 'Journal',
            default => 'Adjustment',
        };
    }
}
