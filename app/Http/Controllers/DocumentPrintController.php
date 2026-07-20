<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SalesReturnStatus;
use App\Enums\VoucherStatus;
use App\Models\Estimate;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\VoucherAllocation;
use App\Services\Settings\AppSettings;
use App\Support\CurrentCompany;
use App\Support\DocumentTotals;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

class DocumentPrintController extends Controller
{
    public function salesInvoice(SalesInvoice $salesInvoice): View
    {
        abort_unless(app(CurrentCompany::class)->canAccessCompany((int) $salesInvoice->company_id, auth()->user()), 403);

        $paid = (float) VoucherAllocation::query()
            ->where('sales_invoice_id', $salesInvoice->id)
            ->whereHas('voucher', fn ($query) => $query->where('status', VoucherStatus::Posted->value))
            ->sum('amount');
        $returned = (float) SalesReturn::withoutGlobalScopes()
            ->where('sales_invoice_id', $salesInvoice->id)
            ->where('status', SalesReturnStatus::Posted->value)
            ->sum('total');

        $salesInvoice->load(['company.bankAccounts', 'customer', 'items.productItem']);
        $invoiceTotals = DocumentTotals::calculate([
            'items' => $salesInvoice->items->toArray(),
            'discount' => $salesInvoice->discount,
        ]);

        return view('sales-invoices.print', [
            'invoice' => $salesInvoice,
            'documentType' => 'invoice',
            'invoiceTotals' => $invoiceTotals,
            'paidAmount' => $paid,
            'dueAmount' => max(0, (float) $invoiceTotals['total'] - $paid - $returned),
            'logoUrl' => AppSettings::storeLogoUrl(),
            'receiptSettings' => AppSettings::receiptSettings(),
        ]);
    }

    public function estimate(Estimate $estimate): View
    {
        abort_unless(app(CurrentCompany::class)->canAccessCompany((int) $estimate->company_id, auth()->user()), 403);

        $estimate->load(['company.bankAccounts', 'customer', 'items.productItem']);
        $estimateTotals = DocumentTotals::calculate([
            'items' => $estimate->items->toArray(),
            'discount' => $estimate->discount,
        ]);

        return view('estimates.print', [
            'estimate' => $estimate,
            'estimateTotals' => $estimateTotals,
            'logoUrl' => AppSettings::storeLogoUrl(),
            'receiptSettings' => AppSettings::receiptSettings(),
        ]);
    }

    public function purchaseInvoice(PurchaseInvoice $purchaseInvoice): View
    {
        return $this->render($purchaseInvoice, 'Purchase Invoice', 'invoice_no', 'invoice_date', 'due_date', 'supplier');
    }

    public function purchaseReturn(PurchaseReturn $purchaseReturn): View
    {
        return $this->render($purchaseReturn, 'Purchase Return', 'return_no', 'return_date', null, 'supplier');
    }

    public function salesReturn(SalesReturn $salesReturn): View
    {
        abort_unless(app(CurrentCompany::class)->canAccessCompany((int) $salesReturn->company_id, auth()->user()), 403);

        $salesReturn->load(['company.bankAccounts', 'customer', 'items.productItem', 'salesInvoice', 'salesInvoices']);
        $returnTotals = DocumentTotals::calculate([
            'items' => $salesReturn->items->toArray(),
            'discount' => 0,
        ]);

        return view('sales-invoices.print', [
            'invoice' => $salesReturn,
            'documentType' => 'credit-note',
            'invoiceTotals' => $returnTotals,
            'paidAmount' => null,
            'dueAmount' => null,
            'logoUrl' => AppSettings::storeLogoUrl(),
            'receiptSettings' => AppSettings::receiptSettings(),
        ]);
    }

    private function render(Model $document, string $title, string $numberColumn, string $dateColumn, ?string $dueDateColumn, string $partyRelation, ?float $paid = null, ?float $due = null, ?array $receiptSettings = null): View
    {
        abort_unless(app(CurrentCompany::class)->canAccessCompany((int) $document->getAttribute('company_id'), auth()->user()), 403);

        $document->load(['company', $partyRelation, 'items.productItem']);

        return view('documents.print', compact('document', 'title', 'numberColumn', 'dateColumn', 'dueDateColumn', 'partyRelation', 'paid', 'due', 'receiptSettings'));
    }
}
