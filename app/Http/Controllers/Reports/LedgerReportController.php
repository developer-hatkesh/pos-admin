<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Supplier;
use App\Services\Reports\BankLedgerReportService;
use App\Services\Reports\CurrencyService;
use App\Services\Reports\CustomerLedgerReportService;
use App\Services\Reports\SupplierLedgerReportService;
use App\Support\CurrentCompany;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LedgerReportController extends Controller
{
    public function customerListingPrint(CustomerLedgerReportService $service): Response
    {
        $rows = $service->query()->orderBy('name')->get()
            ->map(fn (Customer $customer): array => ['party' => $customer, 'summary' => $service->summary($customer, request('from'), request('to'))]);

        return response()->view('reports.ledger.listing-print', [
            'title' => 'Customer Ledger Report',
            'partyType' => 'Customer',
            'rows' => $rows,
            'company' => $this->company(),
            'fromDate' => request('from'),
            'toDate' => request('to'),
        ]);
    }

    public function supplierListingPrint(SupplierLedgerReportService $service): Response
    {
        $rows = $service->query()->orderBy('name')->get()
            ->map(fn (Supplier $supplier): array => ['party' => $supplier, 'summary' => $service->summary($supplier, request('from'), request('to'))]);

        return response()->view('reports.ledger.listing-print', [
            'title' => 'Supplier Ledger Report',
            'partyType' => 'Supplier',
            'rows' => $rows,
            'company' => $this->company(),
            'fromDate' => request('from'),
            'toDate' => request('to'),
        ]);
    }

    public function bankListingPrint(BankLedgerReportService $service): Response
    {
        $rows = $service->query()->orderBy('bank_name')->orderBy('account_name')->get()
            ->map(fn (BankAccount $bankAccount): array => ['party' => $bankAccount, 'summary' => $service->summary($bankAccount, request('from'), request('to'))]);

        return response()->view('reports.ledger.listing-print', [
            'title' => 'Bank Ledger Report',
            'partyType' => 'Bank',
            'rows' => $rows,
            'company' => $this->company(),
            'fromDate' => request('from'),
            'toDate' => request('to'),
        ]);
    }

    public function customerDetailPrint(Customer $customer, CustomerLedgerReportService $service): Response
    {
        $this->authorizeCompany($customer->company_id);
        $customer->loadMissing(['company', 'ledger.parent']);

        return response()->view('reports.ledger.detail-print', [
            'party' => $customer,
            'partyType' => 'Customer',
            'title' => 'Customer Ledger Report',
            'company' => $customer->company,
            'fromDate' => request('from'),
            'toDate' => request('to'),
            ...$service->detail($customer, request('from'), request('to')),
        ]);
    }

    public function supplierDetailPrint(Supplier $supplier, SupplierLedgerReportService $service): Response
    {
        $this->authorizeCompany($supplier->company_id);
        $supplier->loadMissing(['company', 'ledger.parent']);

        return response()->view('reports.ledger.detail-print', [
            'party' => $supplier,
            'partyType' => 'Supplier',
            'title' => 'Supplier Ledger Report',
            'company' => $supplier->company,
            'fromDate' => request('from'),
            'toDate' => request('to'),
            ...$service->detail($supplier, request('from'), request('to')),
        ]);
    }

    public function bankDetailPrint(BankAccount $bankAccount, BankLedgerReportService $service): Response
    {
        $this->authorizeCompany($bankAccount->company_id);
        $bankAccount->loadMissing(['company', 'ledger.parent']);

        return response()->view('reports.ledger.detail-print', [
            'party' => $bankAccount,
            'partyType' => 'Bank',
            'title' => 'Bank Ledger Report',
            'company' => $bankAccount->company,
            'fromDate' => request('from'),
            'toDate' => request('to'),
            ...$service->detail($bankAccount, request('from'), request('to')),
        ]);
    }

    public function customerListingExport(CustomerLedgerReportService $service): StreamedResponse
    {
        return $this->streamListingCsv('customer-ledger.csv', $service, 'Customer');
    }

    public function supplierListingExport(SupplierLedgerReportService $service): StreamedResponse
    {
        return $this->streamListingCsv('supplier-ledger.csv', $service, 'Supplier');
    }

    public function bankListingExport(BankLedgerReportService $service): StreamedResponse
    {
        return $this->streamListingCsv('bank-ledger.csv', $service, 'Bank');
    }

    public function customerDetailExport(Customer $customer, CustomerLedgerReportService $service): StreamedResponse
    {
        $this->authorizeCompany($customer->company_id);

        return $this->streamDetailCsv('customer-ledger-'.$customer->id.'.csv', $customer, $service, 'Customer');
    }

    public function supplierDetailExport(Supplier $supplier, SupplierLedgerReportService $service): StreamedResponse
    {
        $this->authorizeCompany($supplier->company_id);

        return $this->streamDetailCsv('supplier-ledger-'.$supplier->id.'.csv', $supplier, $service, 'Supplier');
    }

    public function bankDetailExport(BankAccount $bankAccount, BankLedgerReportService $service): StreamedResponse
    {
        $this->authorizeCompany($bankAccount->company_id);

        return $this->streamDetailCsv('bank-ledger-'.$bankAccount->id.'.csv', $bankAccount, $service, 'Bank');
    }

    private function streamListingCsv(string $filename, object $service, string $partyType): StreamedResponse
    {
        return response()->streamDownload(function () use ($service, $partyType): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $this->listingHeadings($partyType));

            $orderColumn = $partyType === 'Bank' ? 'bank_name' : 'name';

            $service->query()->orderBy($orderColumn)->chunk(500, function ($rows) use ($out, $service, $partyType): void {
                foreach ($rows as $party) {
                    $summary = $service->summary($party, request('from'), request('to'));
                    fputcsv($out, $this->listingRow($party, $summary, $partyType));
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function listingHeadings(string $partyType): array
    {
        if ($partyType === 'Bank') {
            return ['Bank Name', 'Account Name', 'Account Number', 'Sort Code / IFSC / Bank Code', 'Opening Balance', 'Total Debit', 'Total Credit', 'Closing Balance', 'Dr/Cr', 'Status'];
        }

        return [$partyType.' Name', $partyType.' Code', 'Phone', 'Email', 'Opening Balance', 'Total Debit', 'Total Credit', 'Closing Balance', 'Dr/Cr', 'Status'];
    }

    private function listingRow(object $party, array $summary, string $partyType): array
    {
        $identity = $partyType === 'Bank'
            ? [$party->bank_name, $party->account_name, $party->account_number, $party->sort_code]
            : [$party->name, $partyType === 'Customer' ? $party->customer_code : $party->supplier_code, $party->phone, $party->email];

        return [
            ...$identity,
            $summary['opening_formatted'],
            CurrencyService::format($summary['debit']),
            CurrencyService::format($summary['credit']),
            $summary['closing_formatted'],
            $summary['dr_cr'],
            $party->status?->value ?? $party->status,
        ];
    }

    private function streamDetailCsv(string $filename, object $party, object $service, string $partyType): StreamedResponse
    {
        return response()->streamDownload(function () use ($party, $service): void {
            $out = fopen('php://output', 'w');
            $detail = $service->detail($party, request('from'), request('to'));
            fputcsv($out, ['Date', 'Voucher No.', 'Voucher Type', 'Particulars', 'Debit', 'Credit', 'Balance', 'Dr/Cr']);
            fputcsv($out, ['', '', '', 'Opening Balance', '', '', $detail['summary']['opening_formatted'], $detail['summary']['opening'] === 0.0 ? '' : ($detail['summary']['opening'] > 0 ? 'Dr' : 'Cr')]);

            foreach ($detail['rows'] as $row) {
                fputcsv($out, [
                    optional($row['date'])->format('d-M-Y'),
                    $row['voucher_no'],
                    $row['voucher_type'],
                    $row['particulars'],
                    $row['debit'] > 0 ? CurrencyService::format($row['debit']) : '',
                    $row['credit'] > 0 ? CurrencyService::format($row['credit']) : '',
                    CurrencyService::format(abs($row['balance'])),
                    $row['dr_cr'],
                ]);
            }

            fputcsv($out, ['', '', '', 'Total', CurrencyService::format($detail['summary']['debit']), CurrencyService::format($detail['summary']['credit']), $detail['summary']['closing_formatted'], $detail['summary']['dr_cr']]);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function company(): ?Company
    {
        return Company::query()->find(app(CurrentCompany::class)->id());
    }

    private function authorizeCompany(int $companyId): void
    {
        abort_unless((int) $companyId === (int) app(CurrentCompany::class)->id(), 403);
    }
}
