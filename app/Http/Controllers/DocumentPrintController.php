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
use App\Support\CurrentCompany;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

class DocumentPrintController extends Controller
{
    public function salesInvoice(SalesInvoice $salesInvoice): View
    {
        $paid = (float) VoucherAllocation::query()
            ->where('sales_invoice_id', $salesInvoice->id)
            ->whereHas('voucher', fn ($query) => $query->where('status', VoucherStatus::Posted->value))
            ->sum('amount');
        $returned = (float) SalesReturn::withoutGlobalScopes()
            ->where('sales_invoice_id', $salesInvoice->id)
            ->where('status', SalesReturnStatus::Posted->value)
            ->sum('total');

        return $this->render($salesInvoice, 'Sales Invoice', 'invoice_no', 'invoice_date', 'due_date', 'customer', $paid, max(0, (float) $salesInvoice->total - $paid - $returned));
    }

    public function estimate(Estimate $estimate): View
    {
        return $this->render($estimate, 'Estimate', 'estimate_no', 'estimate_date', 'expiry_date', 'customer');
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
        return $this->render($salesReturn, 'Sales Return', 'return_no', 'return_date', null, 'customer');
    }

    private function render(Model $document, string $title, string $numberColumn, string $dateColumn, ?string $dueDateColumn, string $partyRelation, ?float $paid = null, ?float $due = null): View
    {
        abort_unless(app(CurrentCompany::class)->canAccessCompany((int) $document->getAttribute('company_id'), auth()->user()), 403);

        $document->load(['company', $partyRelation, 'items.productItem']);

        return view('documents.print', compact('document', 'title', 'numberColumn', 'dateColumn', 'dueDateColumn', 'partyRelation', 'paid', 'due'));
    }
}
