<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\CurrencyService;
use App\Services\Reports\ReportDateRangeService;
use App\Services\Reports\VatReportService;
use App\Support\CurrentCompany;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VatReportController extends Controller
{
    public function print(VatReportService $service): Response
    {
        return response()->view('reports.vat.print', [
            'report' => $service->report($this->filters()),
        ]);
    }

    public function export(VatReportService $service): StreamedResponse|Response
    {
        if (request('format') === 'pdf') {
            return $this->print($service);
        }

        $report = $service->report($this->filters());
        $filename = 'vat-report-'.$this->periodSlug().'.csv';

        return response()->streamDownload(function () use ($report): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['VAT Report']);
            fputcsv($out, ['Company', $report['company']?->name ?? config('app.name')]);
            fputcsv($out, ['From', $report['start_date']->format('d-M-Y')]);
            fputcsv($out, ['To', $report['end_date']->format('d-M-Y')]);
            fputcsv($out, ['VAT Type', $report['filters']['vat_type_label']]);
            fputcsv($out, []);

            foreach ($report['sections'] as $section) {
                fputcsv($out, [$section['title']]);
                fputcsv($out, ['Date', 'Type', 'Number', 'Party / Category', 'Net', 'VAT', 'Gross']);

                foreach ($section['rows'] as $row) {
                    fputcsv($out, [
                        optional($row['date'])->format('d-M-Y'),
                        $row['type'],
                        $row['number'],
                        $row['party'],
                        CurrencyService::format($row['net']),
                        CurrencyService::format($row['vat']),
                        CurrencyService::format($row['gross']),
                    ]);
                }

                fputcsv($out, ['Total', '', '', $section['summary']['count'].' rows', CurrencyService::format($section['summary']['net']), CurrencyService::format($section['summary']['vat']), CurrencyService::format($section['summary']['gross'])]);
                fputcsv($out, []);
            }

            fputcsv($out, ['HMRC VAT Boxes']);
            foreach ($report['boxes'] as $box => $data) {
                fputcsv($out, ['Box '.$box, $data['label'], CurrencyService::format($data['amount'])]);
            }

            fputcsv($out, []);
            fputcsv($out, ['Payment Summary']);
            fputcsv($out, ['Receipts', CurrencyService::format($report['payments']['receipts'])]);
            fputcsv($out, ['Payments', CurrencyService::format($report['payments']['payments'])]);
            fputcsv($out, ['Net Cash Flow', CurrencyService::format($report['payments']['net_cash_flow'])]);

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function filters(): array
    {
        $range = $this->period();

        return [
            'company_id' => app(CurrentCompany::class)->id(),
            'start_date' => $range['start_date']->toDateString(),
            'end_date' => $range['end_date']->toDateString(),
            'date_range' => request('date_range', 'this_month'),
            'vat_quarter' => request('vat_quarter', 'date_range'),
            'customer_id' => request('customer_id'),
            'supplier_id' => request('supplier_id'),
            'vat_type' => request('vat_type'),
        ];
    }

    private function period(): array
    {
        $quarter = (string) request('vat_quarter', 'date_range');

        if ($quarter !== 'date_range' && preg_match('/^(?<year>\d{4})-q(?<quarter>[1-4])$/', $quarter, $matches)) {
            $year = (int) $matches['year'];
            $quarterNumber = (int) $matches['quarter'];
            $startMonth = (($quarterNumber - 1) * 3) + 1;
            $start = Carbon::create($year, $startMonth, 1)->startOfDay();
            $end = $start->copy()->addMonthsNoOverflow(2)->endOfMonth()->endOfDay();

            return ['start_date' => $start, 'end_date' => $end, 'slug' => 'vat-q'.$quarterNumber.'-'.$year];
        }

        return app(ReportDateRangeService::class)->resolve(
            request('date_range', 'this_month'),
            request('custom_start_date') ?: request('start_date'),
            request('custom_end_date') ?: request('end_date'),
        );
    }

    private function periodSlug(): string
    {
        return (string) ($this->period()['slug'] ?? 'custom');
    }
}
