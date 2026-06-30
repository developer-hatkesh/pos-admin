<?php

declare(strict_types=1);

use App\Enums\SalesReturnStatus;
use App\Enums\VoucherStatus;
use App\Http\Controllers\AdminCompanySwitchController;
use App\Http\Controllers\LogViewerController;
use App\Http\Controllers\Reports\BalanceSheetReportController;
use App\Http\Controllers\Reports\LedgerReportController;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\VoucherAllocation;
use App\Support\CurrentCompany;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::redirect('/login', '/admin/login')->name('login');

Route::middleware('auth')->post('/admin/switch-company', AdminCompanySwitchController::class)
    ->name('admin.switch-company');

Route::middleware('auth')->get('/logs/{file?}', LogViewerController::class)
    ->where('file', 'laravel-\d{4}-\d{2}-\d{2}\.log')
    ->name('logs.index');

Route::middleware('auth')->get('/admin/sales-invoices/{salesInvoice}/print', function (SalesInvoice $salesInvoice) {
    $user = auth()->user();

    abort_unless(app(CurrentCompany::class)->canAccessCompany((int) $salesInvoice->company_id, $user), 403);

    $paidAmount = round((float) VoucherAllocation::query()
        ->where('sales_invoice_id', $salesInvoice->id)
        ->whereHas('voucher', fn ($query) => $query->where('status', VoucherStatus::Posted->value))
        ->sum('amount'), 2);

    $returnedAmount = round((float) SalesReturn::withoutGlobalScopes()
        ->where('sales_invoice_id', $salesInvoice->id)
        ->where('status', SalesReturnStatus::Posted->value)
        ->sum('total'), 2);

    return view('sales-invoices.print', [
        'invoice' => $salesInvoice->load(['company', 'customer', 'items.productItem']),
        'paidAmount' => $paidAmount,
        'dueAmount' => round(max(0, (float) $salesInvoice->total - $paidAmount - $returnedAmount), 2),
    ]);
})->name('pos.sales-invoices.print');

Route::middleware('auth')->prefix('admin/report-downloads')->name('reports.')->group(function (): void {
    Route::get('customer-ledger/print', [LedgerReportController::class, 'customerListingPrint'])->name('customer-ledger.print');
    Route::get('customer-ledger/export', [LedgerReportController::class, 'customerListingExport'])->name('customer-ledger.export');
    Route::get('customer-ledger/{customer}/print', [LedgerReportController::class, 'customerDetailPrint'])->name('customer-ledger.detail.print');
    Route::get('customer-ledger/{customer}/export', [LedgerReportController::class, 'customerDetailExport'])->name('customer-ledger.detail.export');

    Route::get('supplier-ledger/print', [LedgerReportController::class, 'supplierListingPrint'])->name('supplier-ledger.print');
    Route::get('supplier-ledger/export', [LedgerReportController::class, 'supplierListingExport'])->name('supplier-ledger.export');
    Route::get('supplier-ledger/{supplier}/print', [LedgerReportController::class, 'supplierDetailPrint'])->name('supplier-ledger.detail.print');
    Route::get('supplier-ledger/{supplier}/export', [LedgerReportController::class, 'supplierDetailExport'])->name('supplier-ledger.detail.export');

    Route::get('bank-ledger/print', [LedgerReportController::class, 'bankListingPrint'])->name('bank-ledger.print');
    Route::get('bank-ledger/export', [LedgerReportController::class, 'bankListingExport'])->name('bank-ledger.export');
    Route::get('bank-ledger/{bankAccount}/print', [LedgerReportController::class, 'bankDetailPrint'])->name('bank-ledger.detail.print');
    Route::get('bank-ledger/{bankAccount}/export', [LedgerReportController::class, 'bankDetailExport'])->name('bank-ledger.detail.export');

    Route::get('balance-sheet/print', [BalanceSheetReportController::class, 'print'])->name('balance-sheet.print');
    Route::get('balance-sheet/export', [BalanceSheetReportController::class, 'export'])->name('balance-sheet.export');
});
