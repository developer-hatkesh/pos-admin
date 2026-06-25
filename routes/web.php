<?php

declare(strict_types=1);

use App\Enums\SalesReturnStatus;
use App\Enums\VoucherStatus;
use App\Http\Controllers\AdminCompanySwitchController;
use App\Http\Controllers\LogViewerController;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\VoucherAllocation;
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

    if (! (method_exists($user, 'isAdmin') && $user->isAdmin())) {
        abort_unless((int) $salesInvoice->company_id === (int) $user?->company_id, 403);
    }

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
