<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Supplier;
use App\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;

class SupplierLedgerReportService
{
    public function __construct(private readonly LedgerBalanceService $balances) {}

    public function query(): Builder
    {
        return Supplier::query()
            ->with(['ledger.parent'])
            ->where('company_id', app(CurrentCompany::class)->id());
    }

    public function summary(Supplier $supplier, ?string $fromDate = null, ?string $toDate = null): array
    {
        $ledger = $supplier->ledger;

        if ($ledger === null) {
            return $this->emptySummary();
        }

        $opening = $this->balances->openingBalance($ledger, $fromDate);
        $totals = $this->balances->periodTotals($ledger, $fromDate, $toDate);
        $closing = $this->balances->closingBalance($ledger, $fromDate, $toDate);

        return [
            'opening' => $opening,
            'debit' => $totals['debit'],
            'credit' => $totals['credit'],
            'closing' => $closing,
            'dr_cr' => $this->balances->balanceType($closing),
            'opening_formatted' => $this->balances->formattedBalance($opening),
            'closing_formatted' => $this->balances->formattedBalance($closing),
        ];
    }

    public function detail(Supplier $supplier, ?string $fromDate = null, ?string $toDate = null): array
    {
        $summary = $this->summary($supplier, $fromDate, $toDate);
        $rows = $supplier->ledger
            ? $this->balances->runningRows($supplier->ledger, $fromDate, $toDate)
            : collect();

        return compact('summary', 'rows');
    }

    private function emptySummary(): array
    {
        return [
            'opening' => 0.0, 'debit' => 0.0, 'credit' => 0.0, 'closing' => 0.0, 'dr_cr' => '',
            'opening_formatted' => CurrencyService::format(0), 'closing_formatted' => CurrencyService::format(0),
        ];
    }
}
