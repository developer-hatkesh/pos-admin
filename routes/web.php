<?php

declare(strict_types=1);

use App\Http\Controllers\AdminCompanySwitchController;
use App\Http\Controllers\DocumentPrintController;
use App\Http\Controllers\LogViewerController;
use App\Http\Controllers\Reports\BalanceSheetReportController;
use App\Http\Controllers\Reports\DailySummaryReportController;
use App\Http\Controllers\Reports\LedgerReportController;
use App\Http\Controllers\Reports\VatReportController;
use App\Http\Middleware\SetPermissionCompany;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::redirect('/login', '/admin/login')->name('login');

Route::middleware('auth')->post('/admin/switch-company', AdminCompanySwitchController::class)
    ->name('admin.switch-company');

Route::middleware('auth')->controller(DocumentPrintController::class)->group(function (): void {
    Route::get('/admin/estimates/{estimate}/print', 'estimate')->name('estimates.print');
    Route::get('/admin/purchase-invoices/{purchaseInvoice}/print', 'purchaseInvoice')->name('purchase-invoices.print');
    Route::get('/admin/purchase-returns/{purchaseReturn}/print', 'purchaseReturn')->name('purchase-returns.print');
    Route::get('/admin/sales-returns/{salesReturn}/print', 'salesReturn')->name('sales-returns.print');
});

Route::middleware('auth')->get('/logs/{file?}', LogViewerController::class)
    ->where('file', 'laravel-\d{4}-\d{2}-\d{2}\.log')
    ->name('logs.index');

Route::middleware('auth')->get('/admin/sales-invoices/{salesInvoice}/print', [DocumentPrintController::class, 'salesInvoice'])
    ->name('pos.sales-invoices.print');

Route::middleware(['auth', SetPermissionCompany::class])->prefix('admin/report-downloads')->name('reports.')->group(function (): void {
    Route::get('summary/print', [DailySummaryReportController::class, 'print'])->name('summary.print');
    Route::get('summary/export', [DailySummaryReportController::class, 'export'])->name('summary.export');

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

    Route::get('vat-report/print', [VatReportController::class, 'print'])->name('vat-report.print');
    Route::get('vat-report/export', [VatReportController::class, 'export'])->name('vat-report.export');
});
