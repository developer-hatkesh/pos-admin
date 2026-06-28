<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\BalanceSheetService;
use App\Services\Reports\CurrencyService;
use App\Services\Reports\ReportDateRangeService;
use App\Support\CurrentCompany;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BalanceSheetReportController extends Controller
{
    public function print(BalanceSheetService $service): Response
    {
        return response()->view('reports.balance-sheet.print', [
            'report' => $this->report($service),
        ]);
    }

    public function export(BalanceSheetService $service): StreamedResponse|Response
    {
        $format = request('format', 'csv');

        if ($format === 'pdf') {
            return $this->print($service);
        }

        $report = $this->report($service);
        $filename = 'balance-sheet-'.$this->dateRange()['slug'].'.csv';

        return response()->streamDownload(function () use ($report): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Balance Sheet']);
            fputcsv($out, ['As On Date', $report['as_on_date']->format('d-M-Y')]);
            fputcsv($out, []);
            fputcsv($out, ['Section', 'Group', 'Account Group', 'Ledger Code', 'Ledger', 'Amount']);

            foreach ($report['sections'] as $section) {
                foreach ($section['groups'] as $group) {
                    fputcsv($out, [$section['title'], $group['name'], '', '', '', CurrencyService::format($group['amount'])]);

                    foreach ($group['categories'] as $category) {
                        fputcsv($out, [$section['title'], $group['name'], $category['name'], '', '', CurrencyService::format($category['amount'])]);

                        foreach ($category['ledgers'] as $ledger) {
                            fputcsv($out, [$section['title'], $group['name'], $category['name'], $ledger['code'], $ledger['name'], CurrencyService::format($ledger['statement_amount'])]);
                        }
                    }
                }
            }

            fputcsv($out, []);
            fputcsv($out, ['Total Assets', CurrencyService::format($report['summary']['total_assets'])]);
            fputcsv($out, ['Total Liabilities', CurrencyService::format($report['summary']['total_liabilities'])]);
            fputcsv($out, ['Total Equity', CurrencyService::format($report['summary']['total_equity'])]);
            fputcsv($out, ['Total Liabilities + Equity', CurrencyService::format($report['summary']['total_liabilities_equity'])]);
            fputcsv($out, ['Difference', CurrencyService::format($report['summary']['difference'])]);

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function report(BalanceSheetService $service): array
    {
        $companyId = app(CurrentCompany::class)->id();

        abort_unless($companyId !== null, 403);

        return $service->report(
            companyId: $companyId,
            asOnDate: $this->dateRange()['end'],
            showZeroBalances: request()->boolean('show_zero'),
        );
    }

    private function dateRange(): array
    {
        $range = request('date_range');

        if (blank($range) && (request()->has('as_on_date') || request()->has('end_date'))) {
            $range = 'custom';
        }

        $resolved = app(ReportDateRangeService::class)->resolve(
            $range ?: 'today',
            request('custom_start_date') ?: request('start_date'),
            request('custom_end_date') ?: request('end_date') ?: request('as_on_date'),
        );

        return [
            'end' => $resolved['end_date']->toDateString(),
            'slug' => 'as-at-'.$resolved['end_date']->format('Y-m-d'),
        ];
    }
}
