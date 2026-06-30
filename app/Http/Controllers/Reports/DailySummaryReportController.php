<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\CurrencyService;
use App\Services\Reports\DailySummaryReportService;
use App\Services\Reports\ReportDateRangeService;
use App\Support\CurrentCompany;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DailySummaryReportController extends Controller
{
    public function print(DailySummaryReportService $service): Response
    {
        return response()->view('reports.summary.print', [
            'report' => $this->report($service),
        ]);
    }

    public function export(DailySummaryReportService $service): StreamedResponse|Response
    {
        if (request('format') === 'pdf') {
            return $this->print($service);
        }

        $report = $this->report($service);
        $filename = 'summary-report-'.$this->dateRange()['slug'].'.csv';

        return response()->streamDownload(function () use ($report): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Summary Report']);
            fputcsv($out, ['Company', $report['company']?->name ?? config('app.name')]);
            fputcsv($out, ['From', $report['start_date']->format('d-M-Y')]);
            fputcsv($out, ['To', $report['end_date']->format('d-M-Y')]);
            fputcsv($out, []);
            fputcsv($out, ['Metric', 'Amount']);
            fputcsv($out, ['Total Sale', CurrencyService::format($report['sales']['total'])]);
            fputcsv($out, ['Cash', CurrencyService::format($report['sales']['cash'])]);
            fputcsv($out, ['Credit', CurrencyService::format($report['sales']['credit'])]);
            fputcsv($out, ['Bank Transfer', CurrencyService::format($report['sales']['bank_transfer'])]);
            fputcsv($out, ['Card Payment', CurrencyService::format($report['sales']['card_payment'])]);
            fputcsv($out, ['Expenses', CurrencyService::format($report['outgoings']['expenses'])]);
            fputcsv($out, ['Wages', CurrencyService::format($report['outgoings']['wages'])]);
            fputcsv($out, ['Total Quantity of Perfume Sold', number_format((float) $report['stock']['perfume_qty_sold'], 3)]);
            fputcsv($out, []);
            fputcsv($out, ['Bank', 'Balance']);

            foreach ($report['bank_balances'] as $bank) {
                fputcsv($out, [$bank['name'], CurrencyService::format($bank['amount'])]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function report(DailySummaryReportService $service): array
    {
        $companyId = app(CurrentCompany::class)->id();

        abort_unless($companyId !== null, 403);

        $range = $this->dateRange();

        return $service->report($companyId, $range['start'], $range['end']);
    }

    private function dateRange(): array
    {
        $resolved = app(ReportDateRangeService::class)->resolve(
            request('date_range', 'today'),
            request('custom_start_date') ?: request('start_date'),
            request('custom_end_date') ?: request('end_date'),
        );

        return [
            'start' => $resolved['start_date'],
            'end' => $resolved['end_date'],
            'slug' => $resolved['slug'],
        ];
    }
}
