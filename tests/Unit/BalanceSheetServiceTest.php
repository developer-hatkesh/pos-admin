<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Reports\BalanceSheetService;
use App\Services\Reports\FinancialStatementService;
use ReflectionMethod;
use Tests\TestCase;

class BalanceSheetServiceTest extends TestCase
{
    public function test_summary_boxes_use_ledger_identity_inside_broad_categories(): void
    {
        $service = new BalanceSheetService($this->createMock(FinancialStatementService::class));
        $summary = (new ReflectionMethod($service, 'summary'))->invoke($service, $this->sections());

        $this->assertSame(25.0, $summary['cash_balance']);
        $this->assertSame(100.0, $summary['bank_balance']);
        $this->assertSame(250.0, $summary['accounts_receivable']);
        $this->assertSame(80.0, $summary['accounts_payable']);
        $this->assertSame(40.0, $summary['inventory']);
    }

    private function sections(): array
    {
        return [
            'assets' => ['groups' => [
                'asset_current' => $this->group(415, [
                    $this->category('Current Assets', [
                        $this->ledger('Cash', 25),
                        $this->ledger('Bank', 100),
                        $this->ledger('Trade Debtors', 250),
                        $this->ledger('Stock', 40),
                    ]),
                ]),
                'asset_non_current' => $this->group(0),
            ]],
            'liabilities_equity' => ['groups' => [
                'liability_current' => $this->group(80, [
                    $this->category('Current Liabilities', [$this->ledger('Trade Creditors', 80)]),
                ]),
                'liability_non_current' => $this->group(50, [
                    $this->category('Non Current Liabilities', [$this->ledger('Bank Loan', 50)]),
                ]),
                'equity' => $this->group(285),
            ]],
        ];
    }

    private function group(float $amount, array $categories = []): array
    {
        return ['amount' => $amount, 'categories' => $categories];
    }

    private function category(string $name, array $ledgers): array
    {
        return ['name' => $name, 'amount' => array_sum(array_column($ledgers, 'statement_amount')), 'ledgers' => $ledgers];
    }

    private function ledger(string $name, float $amount): array
    {
        return ['name' => $name, 'code' => '', 'category_name' => 'Current', 'statement_amount' => $amount];
    }
}
