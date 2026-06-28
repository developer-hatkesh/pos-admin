<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Company;
use App\Support\CurrentCompany;
use Illuminate\Support\Carbon;

class BalanceSheetService
{
    public function __construct(private readonly FinancialStatementService $statements) {}

    public function report(?int $companyId, string $asOnDate, bool $showZeroBalances = false): array
    {
        $companyId ??= app(CurrentCompany::class)->id();

        abort_unless($companyId !== null, 403);

        $sections = $this->statements->balanceSheetRows($companyId, $asOnDate, $showZeroBalances);
        $summary = $this->summary($sections);
        $difference = round($summary['total_assets'] - $summary['total_liabilities_equity'], 2);

        return [
            'company' => Company::query()->find($companyId),
            'as_on_date' => Carbon::parse($asOnDate),
            'generated_at' => now(),
            'sections' => $sections,
            'summary' => [
                ...$summary,
                'difference' => $difference,
                'is_balanced' => abs($difference) < 0.01,
            ],
            'ratios' => $this->ratios($summary),
        ];
    }

    private function summary(array $sections): array
    {
        $assetGroups = $sections['assets']['groups'];
        $liabilityGroups = $sections['liabilities_equity']['groups'];

        $currentAssets = (float) $assetGroups['asset_current']['amount'];
        $nonCurrentAssets = (float) $assetGroups['asset_non_current']['amount'];
        $currentLiabilities = (float) $liabilityGroups['liability_current']['amount'];
        $nonCurrentLiabilities = (float) $liabilityGroups['liability_non_current']['amount'];
        $equity = (float) $liabilityGroups['equity']['amount'];
        $totalAssets = round($currentAssets + $nonCurrentAssets, 2);
        $totalLiabilities = round($currentLiabilities + $nonCurrentLiabilities, 2);

        return [
            'current_assets' => round($currentAssets, 2),
            'non_current_assets' => round($nonCurrentAssets, 2),
            'total_assets' => $totalAssets,
            'current_liabilities' => round($currentLiabilities, 2),
            'non_current_liabilities' => round($nonCurrentLiabilities, 2),
            'total_liabilities' => $totalLiabilities,
            'total_equity' => round($equity, 2),
            'total_liabilities_equity' => round($totalLiabilities + $equity, 2),
            'working_capital' => round($currentAssets - $currentLiabilities, 2),
            'cash_balance' => $this->categoryTotal($sections, ['cash']),
            'bank_balance' => $this->categoryTotal($sections, ['bank']),
            'accounts_receivable' => $this->categoryTotal($sections, ['receivable', 'debtor']),
            'accounts_payable' => $this->categoryTotal($sections, ['payable', 'creditor']),
            'inventory' => $this->categoryTotal($sections, ['inventory', 'stock']),
        ];
    }

    private function ratios(array $summary): array
    {
        $currentLiabilities = (float) $summary['current_liabilities'];
        $totalAssets = (float) $summary['total_assets'];
        $totalEquity = (float) $summary['total_equity'];
        $totalLiabilities = (float) $summary['total_liabilities'];
        $quickAssets = (float) $summary['current_assets'] - (float) $summary['inventory'];

        return [
            'current_ratio' => $this->ratio((float) $summary['current_assets'], $currentLiabilities),
            'quick_ratio' => $this->ratio($quickAssets, $currentLiabilities),
            'debt_ratio' => $this->ratio($totalLiabilities, $totalAssets),
            'debt_to_equity' => $this->ratio($totalLiabilities, $totalEquity),
            'net_assets' => round($totalAssets - $totalLiabilities, 2),
        ];
    }

    private function ratio(float $numerator, float $denominator): ?float
    {
        if (round($denominator, 2) === 0.0) {
            return null;
        }

        return round($numerator / $denominator, 2);
    }

    private function categoryTotal(array $sections, array $needles): float
    {
        $total = 0.0;

        foreach ($sections as $section) {
            foreach ($section['groups'] as $group) {
                foreach ($group['categories'] as $category) {
                    $name = strtolower((string) $category['name']);

                    foreach ($needles as $needle) {
                        if (str_contains($name, $needle)) {
                            $total += (float) $category['amount'];
                            break;
                        }
                    }
                }
            }
        }

        return round($total, 2);
    }
}
