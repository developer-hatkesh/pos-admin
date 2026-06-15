<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Models\SalesInvoice;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->get('/admin/sales-invoices/{salesInvoice}/print', function (SalesInvoice $salesInvoice) {
    $user = auth()->user();

    if (! (method_exists($user, 'isAdmin') && $user->isAdmin())) {
        abort_unless((int) $salesInvoice->company_id === (int) $user?->company_id, 403);
    }

    return view('sales-invoices.print', [
        'invoice' => $salesInvoice->load(['company', 'customer', 'items.productItem']),
    ]);
})->name('pos.sales-invoices.print');
