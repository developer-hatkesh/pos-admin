<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\LedgerType;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FinancialStatementService
{
    public function __construct(private readonly BalanceCalculatorService $calculator) {}

    public function balanceSheetRows(int $companyId, string $asOnDate, bool $showZeroBalances = false): array
    {
        $ledgers = $this->calculator->ledgerBalances($companyId, $asOnDate);
        $balanceSheetLedgers = $ledgers
            ->filter(fn (array $ledger): bool => in_array($ledger['type'], [
                LedgerType::Asset->value,
                LedgerType::Liability->value,
                LedgerType::Equity->value,
            ], true))
            ->when(! $showZeroBalances, fn (Collection $rows): Collection => $rows->filter(
                fn (array $ledger): bool => round((float) $ledger['statement_amount'], 2) !== 0.0
            ));

        $sections = [
            'assets' => [
                'title' => 'Assets',
                'tone' => 'green',
                'groups' => [
                    'asset_non_current' => $this->emptyGroup('asset_non_current', 'Non Current Assets'),
                    'asset_current' => $this->emptyGroup('asset_current', 'Current Assets'),
                ],
            ],
            'liabilities_equity' => [
                'title' => 'Liabilities & Equity',
                'tone' => 'red',
                'groups' => [
                    'liability_non_current' => $this->emptyGroup('liability_non_current', 'Non Current Liabilities'),
                    'liability_current' => $this->emptyGroup('liability_current', 'Current Liabilities'),
                    'equity' => $this->emptyGroup('equity', "Owner's Equity"),
                ],
            ],
        ];

        foreach ($balanceSheetLedgers as $ledger) {
            $groupKey = $this->sectionGroupKey($ledger);
            $side = $ledger['type'] === LedgerType::Asset->value ? 'assets' : 'liabilities_equity';

            $categoryKey = $groupKey.'_'.$this->slug($ledger['category_name']);
            $category = $sections[$side]['groups'][$groupKey]['categories'][$categoryKey]
                ?? $this->emptyCategory($categoryKey, $ledger['category_name']);

            $category['ledgers'][] = $ledger;
            $category['amount'] = round($category['amount'] + (float) $ledger['statement_amount'], 2);
            $sections[$side]['groups'][$groupKey]['categories'][$categoryKey] = $category;
            $sections[$side]['groups'][$groupKey]['amount'] = round($sections[$side]['groups'][$groupKey]['amount'] + (float) $ledger['statement_amount'], 2);
        }

        $currentYearProfit = $this->currentYearProfit($ledgers);

        if ($showZeroBalances || round($currentYearProfit, 2) !== 0.0) {
            $equityKey = 'equity_current_year_profit_loss';
            $sections['liabilities_equity']['groups']['equity']['categories'][$equityKey] = [
                'key' => $equityKey,
                'name' => 'Current Year Profit / Loss',
                'amount' => $currentYearProfit,
                'ledgers' => [[
                    'id' => null,
                    'name' => 'Current Year Profit / Loss',
                    'code' => '',
                    'type' => LedgerType::Equity->value,
                    'category_name' => 'Current Year Profit / Loss',
                    'statement_amount' => $currentYearProfit,
                    'signed_balance' => -$currentYearProfit,
                    'drill_url' => $this->financialReportsUrl(),
                ]],
                'drill_url' => $this->financialReportsUrl(),
            ];

            $sections['liabilities_equity']['groups']['equity']['amount'] = round(
                $sections['liabilities_equity']['groups']['equity']['amount'] + $currentYearProfit,
                2,
            );
        }

        $this->decorate($sections);

        return $sections;
    }

    private function currentYearProfit(Collection $ledgers): float
    {
        $signedIncomeAndExpense = $ledgers
            ->filter(fn (array $ledger): bool => in_array($ledger['type'], [LedgerType::Income->value, LedgerType::Expense->value], true))
            ->sum('signed_balance');

        return round(-1 * (float) $signedIncomeAndExpense, 2);
    }

    private function sectionGroupKey(array $ledger): string
    {
        if ($ledger['type'] === LedgerType::Equity->value) {
            return 'equity';
        }

        $category = Str::lower(trim($ledger['category_code'].' '.$ledger['category_name']));
        $isNonCurrent = Str::contains($category, ['noncur', 'non-current', 'fixed', 'long term', 'long-term']);

        if ($ledger['type'] === LedgerType::Asset->value) {
            return $isNonCurrent ? 'asset_non_current' : 'asset_current';
        }

        return $isNonCurrent ? 'liability_non_current' : 'liability_current';
    }

    private function decorate(array &$sections): void
    {
        foreach ($sections as &$section) {
            foreach ($section['groups'] as &$group) {
                $group['categories'] = collect($group['categories'])
                    ->map(function (array $category): array {
                        $category['drill_url'] = $this->drillUrl($category['name']);
                        $category['ledgers'] = collect($category['ledgers'])
                            ->map(function (array $ledger): array {
                                $ledger['drill_url'] = $this->drillUrl($ledger['category_name'], $ledger['id']);

                                return $ledger;
                            })
                            ->values()
                            ->all();

                        return $category;
                    })
                    ->sortBy('name')
                    ->values()
                    ->all();
            }
        }
    }

    private function emptyGroup(string $key, string $name): array
    {
        return ['key' => $key, 'name' => $name, 'amount' => 0.0, 'categories' => []];
    }

    private function emptyCategory(string $key, string $name): array
    {
        return ['key' => $key, 'name' => $name, 'amount' => 0.0, 'ledgers' => []];
    }

    private function slug(string $value): string
    {
        return (string) Str::of($value)->slug('_');
    }

    private function drillUrl(string $name, ?int $ledgerId = null): string
    {
        $name = Str::lower($name);

        if (Str::contains($name, ['receivable', 'debtor', 'customer'])) {
            return route('filament.admin.resources.reports.customer-ledger.index');
        }

        if (Str::contains($name, ['payable', 'creditor', 'supplier'])) {
            return route('filament.admin.resources.reports.supplier-ledger.index');
        }

        if (Str::contains($name, ['bank', 'cash'])) {
            return route('filament.admin.resources.reports.bank-ledger.index');
        }

        return $this->financialReportsUrl();
    }

    private function financialReportsUrl(): string
    {
        return route('filament.admin.resources.financial-reports.index');
    }
}
