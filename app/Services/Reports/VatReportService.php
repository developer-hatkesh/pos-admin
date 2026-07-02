<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\ExpenseStatus;
use App\Enums\IncomeStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PurchaseReturnStatus;
use App\Enums\SalesReturnStatus;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\Voucher;
use App\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class VatReportService
{
    public function report(array $filters): array
    {
        $companyId = (int) ($filters['company_id'] ?? app(CurrentCompany::class)->id());
        abort_unless($companyId > 0, 403);

        $start = Carbon::parse($filters['start_date'] ?? now()->startOfMonth())->startOfDay();
        $end = Carbon::parse($filters['end_date'] ?? now()->endOfMonth())->endOfDay();
        $taxRateId = filled($filters['vat_type'] ?? null) ? (int) $filters['vat_type'] : null;

        $sales = $this->salesRows($companyId, $start, $end, $filters, $taxRateId);
        $purchases = $this->purchaseRows($companyId, $start, $end, $filters, $taxRateId);
        $expenses = $this->expenseRows($companyId, $start, $end, $filters);
        $income = $this->incomeRows($companyId, $start, $end, $filters);
        $salesReturns = $this->salesReturnRows($companyId, $start, $end, $filters, $taxRateId);
        $purchaseReturns = $this->purchaseReturnRows($companyId, $start, $end, $filters, $taxRateId);

        $salesSummary = $this->summarise($sales);
        $purchaseSummary = $this->summarise($purchases);
        $expenseSummary = $this->summarise($expenses);
        $incomeSummary = $this->summarise($income);
        $salesReturnSummary = $this->summarise($salesReturns);
        $purchaseReturnSummary = $this->summarise($purchaseReturns);

        $box1 = $this->round($salesSummary['vat'] + $incomeSummary['vat'] - $salesReturnSummary['vat']);
        $box2 = 0.0; // TODO: No EU acquisition VAT fields exist in the current schema.
        $box4 = $this->round($purchaseSummary['vat'] + $expenseSummary['vat'] - $purchaseReturnSummary['vat']);
        $box6 = $this->round($salesSummary['net'] + $incomeSummary['net'] - $salesReturnSummary['net']);
        $box7 = $this->round($purchaseSummary['net'] + $expenseSummary['net'] - $purchaseReturnSummary['net']);

        return [
            'company' => Company::query()->find($companyId),
            'start_date' => $start,
            'end_date' => $end,
            'generated_at' => now(),
            'filters' => [
                ...$filters,
                'vat_type_label' => $taxRateId ? TaxRate::query()->whereKey($taxRateId)->value('name') : 'All VAT rates',
                'location_available' => false, // TODO: Add location filtering when branch / warehouse columns are added.
            ],
            'sections' => [
                'sales' => ['title' => 'Sales / Output VAT', 'rows' => $sales, 'summary' => $salesSummary],
                'purchases' => ['title' => 'Purchases / Input VAT', 'rows' => $purchases, 'summary' => $purchaseSummary],
                'expenses' => ['title' => 'Expenses', 'rows' => $expenses, 'summary' => $expenseSummary],
                'income' => ['title' => 'Other Income', 'rows' => $income, 'summary' => $incomeSummary],
                'credit_notes' => ['title' => 'Credit Notes (Sales Returns)', 'rows' => $salesReturns, 'summary' => $salesReturnSummary],
                'debit_notes' => ['title' => 'Debit Notes (Purchase Returns)', 'rows' => $purchaseReturns, 'summary' => $purchaseReturnSummary],
                'imports_exports' => ['title' => 'Imports / Exports', 'rows' => collect(), 'summary' => $this->emptySummary(), 'note' => 'No import/export fields exist in the current schema.'],
            ],
            'boxes' => [
                1 => ['label' => 'VAT due on sales and other outputs', 'amount' => $box1],
                2 => ['label' => 'VAT due on EU acquisitions', 'amount' => $box2],
                3 => ['label' => 'Total VAT due', 'amount' => $this->round($box1 + $box2)],
                4 => ['label' => 'VAT reclaimed on purchases and expenses', 'amount' => $box4],
                5 => ['label' => 'VAT to pay / reclaim', 'amount' => $this->round($box1 + $box2 - $box4)],
                6 => ['label' => 'Total sales excluding VAT', 'amount' => $box6],
                7 => ['label' => 'Total purchases excluding VAT', 'amount' => $box7],
                8 => ['label' => 'EU supplies', 'amount' => 0.0], // TODO: No EU supplies fields exist in the current schema.
                9 => ['label' => 'EU acquisitions', 'amount' => 0.0], // TODO: No EU acquisitions fields exist in the current schema.
            ],
            'overall' => [
                'output_vat' => $this->round($salesSummary['vat'] + $incomeSummary['vat']),
                'input_vat' => $this->round($purchaseSummary['vat'] + $expenseSummary['vat']),
                'adjustment_vat' => $this->round($salesReturnSummary['vat'] + $purchaseReturnSummary['vat']),
                'net_vat' => $this->round($box1 + $box2 - $box4),
                'taxable_outputs' => $box6,
                'taxable_inputs' => $box7,
            ],
            'charts' => $this->chartData($salesSummary, $purchaseSummary, $expenseSummary, $incomeSummary, $salesReturnSummary, $purchaseReturnSummary),
            'payments' => $this->paymentSummary($companyId, $start, $end, $filters),
        ];
    }

    public function customerOptions(int $companyId): array
    {
        return Customer::withoutGlobalScopes()->where('company_id', $companyId)->orderBy('name')->pluck('name', 'id')->all();
    }

    public function supplierOptions(int $companyId): array
    {
        return Supplier::withoutGlobalScopes()->where('company_id', $companyId)->orderBy('name')->pluck('name', 'id')->all();
    }

    public function vatTypeOptions(): array
    {
        return TaxRate::query()
            ->orderBy('rate')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (TaxRate $rate): array => [$rate->id => $rate->name.' ('.number_format((float) $rate->rate, 2).'%)'])
            ->all();
    }

    private function salesRows(int $companyId, Carbon $start, Carbon $end, array $filters, ?int $taxRateId): Collection
    {
        // Uses sales_invoices.invoice_date/status/customer_id and sales_invoice_items line VAT columns.
        return SalesInvoice::withoutGlobalScopes()
            ->with(['customer', 'items'])
            ->where('company_id', $companyId)
            ->whereBetween('invoice_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('status', [InvoiceStatus::Posted->value, InvoiceStatus::Paid->value, InvoiceStatus::Partial->value])
            ->when(filled($filters['customer_id'] ?? null), fn (Builder $query): Builder => $query->where('customer_id', (int) $filters['customer_id']))
            ->when($taxRateId, fn (Builder $query): Builder => $query->whereHas('items', fn (Builder $items): Builder => $items->where('tax_rate_id', $taxRateId)))
            ->orderBy('invoice_date')
            ->orderBy('invoice_no')
            ->get()
            ->map(fn (SalesInvoice $invoice): array => $this->invoiceRow(
                'Sales Invoice',
                $invoice->invoice_date,
                $invoice->invoice_no,
                $invoice->customer?->name,
                $this->salesInvoiceAmounts($invoice, $taxRateId),
            ));
    }

    private function purchaseRows(int $companyId, Carbon $start, Carbon $end, array $filters, ?int $taxRateId): Collection
    {
        // Uses purchase_invoices.invoice_date/status/supplier_id and purchase_invoice_items line VAT columns.
        return PurchaseInvoice::withoutGlobalScopes()
            ->with(['supplier', 'items'])
            ->where('company_id', $companyId)
            ->whereBetween('invoice_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('status', [InvoiceStatus::Posted->value, InvoiceStatus::Paid->value, InvoiceStatus::Partial->value])
            ->when(filled($filters['supplier_id'] ?? null), fn (Builder $query): Builder => $query->where('supplier_id', (int) $filters['supplier_id']))
            ->when($taxRateId, fn (Builder $query): Builder => $query->whereHas('items', fn (Builder $items): Builder => $items->where('tax_rate_id', $taxRateId)))
            ->orderBy('invoice_date')
            ->orderBy('invoice_no')
            ->get()
            ->map(fn (PurchaseInvoice $invoice): array => $this->invoiceRow(
                'Purchase Invoice',
                $invoice->invoice_date,
                $invoice->invoice_no,
                $invoice->supplier?->name,
                $this->purchaseInvoiceAmounts($invoice, $taxRateId),
            ));
    }

    private function expenseRows(int $companyId, Carbon $start, Carbon $end, array $filters): Collection
    {
        // Uses expenses.expense_date, sub_total_amount, tax_amount, grand_total_amount and supplier_id.
        // TODO: expenses has no tax_rate_id column, so VAT type filtering cannot be applied here.
        return Expense::withoutGlobalScopes()
            ->with(['supplier', 'category'])
            ->where('company_id', $companyId)
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('status', [ExpenseStatus::Posted->value, ExpenseStatus::Paid->value])
            ->when(filled($filters['supplier_id'] ?? null), fn (Builder $query): Builder => $query->where('supplier_id', (int) $filters['supplier_id']))
            ->orderBy('expense_date')
            ->orderBy('voucher_no')
            ->get()
            ->map(fn (Expense $expense): array => $this->invoiceRow(
                'Expense',
                $expense->expense_date,
                $expense->voucher_no,
                $expense->supplier?->name ?: $expense->category?->category_name,
                [
                    'net' => (float) $expense->sub_total_amount,
                    'vat' => (float) $expense->tax_amount,
                    'gross' => (float) $expense->grand_total_amount,
                ],
            ));
    }

    private function incomeRows(int $companyId, Carbon $start, Carbon $end, array $filters): Collection
    {
        // Uses incomes.income_date, sub_total_amount, tax_amount, grand_total_amount and category.
        // TODO: incomes has no customer_id or tax_rate_id columns, so customer/VAT type filtering cannot be applied here.
        return Income::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereBetween('income_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('status', [IncomeStatus::Posted->value, IncomeStatus::Paid->value])
            ->orderBy('income_date')
            ->orderBy('voucher_no')
            ->get()
            ->map(fn (Income $income): array => $this->invoiceRow(
                'Other Income',
                $income->income_date,
                $income->voucher_no,
                $income->category,
                [
                    'net' => (float) $income->sub_total_amount,
                    'vat' => (float) $income->tax_amount,
                    'gross' => (float) $income->grand_total_amount,
                ],
            ));
    }

    private function salesReturnRows(int $companyId, Carbon $start, Carbon $end, array $filters, ?int $taxRateId): Collection
    {
        // Uses sales_returns.return_date/status/customer_id and sales_return_items line VAT columns.
        return SalesReturn::withoutGlobalScopes()
            ->with(['customer', 'items'])
            ->where('company_id', $companyId)
            ->whereBetween('return_date', [$start->toDateString(), $end->toDateString()])
            ->where('status', SalesReturnStatus::Posted->value)
            ->when(filled($filters['customer_id'] ?? null), fn (Builder $query): Builder => $query->where('customer_id', (int) $filters['customer_id']))
            ->when($taxRateId, fn (Builder $query): Builder => $query->whereHas('items', fn (Builder $items): Builder => $items->where('tax_rate_id', $taxRateId)))
            ->orderBy('return_date')
            ->orderBy('return_no')
            ->get()
            ->map(fn (SalesReturn $return): array => $this->invoiceRow(
                'Sales Credit Note',
                $return->return_date,
                $return->return_no,
                $return->customer?->name,
                $this->salesReturnAmounts($return, $taxRateId),
            ));
    }

    private function purchaseReturnRows(int $companyId, Carbon $start, Carbon $end, array $filters, ?int $taxRateId): Collection
    {
        // Uses purchase_returns.return_date/status/supplier_id and purchase_return_items line VAT columns.
        return PurchaseReturn::withoutGlobalScopes()
            ->with(['supplier', 'items'])
            ->where('company_id', $companyId)
            ->whereBetween('return_date', [$start->toDateString(), $end->toDateString()])
            ->where('status', PurchaseReturnStatus::Posted->value)
            ->when(filled($filters['supplier_id'] ?? null), fn (Builder $query): Builder => $query->where('supplier_id', (int) $filters['supplier_id']))
            ->when($taxRateId, fn (Builder $query): Builder => $query->whereHas('items', fn (Builder $items): Builder => $items->where('tax_rate_id', $taxRateId)))
            ->orderBy('return_date')
            ->orderBy('return_no')
            ->get()
            ->map(fn (PurchaseReturn $return): array => $this->invoiceRow(
                'Purchase Debit Note',
                $return->return_date,
                $return->return_no,
                $return->supplier?->name,
                $this->purchaseReturnAmounts($return, $taxRateId),
            ));
    }

    private function paymentSummary(int $companyId, Carbon $start, Carbon $end, array $filters): array
    {
        // Uses vouchers.voucher_date/voucher_type/status/amount/customer_id/supplier_id.
        $vouchers = Voucher::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereBetween('voucher_date', [$start->toDateString(), $end->toDateString()])
            ->where('status', VoucherStatus::Posted->value)
            ->when(filled($filters['customer_id'] ?? null), fn (Builder $query): Builder => $query->where('customer_id', (int) $filters['customer_id']))
            ->when(filled($filters['supplier_id'] ?? null), fn (Builder $query): Builder => $query->where('supplier_id', (int) $filters['supplier_id']))
            ->get();

        $receipts = $this->round((float) $vouchers
            ->filter(fn (Voucher $voucher): bool => $this->enumValue($voucher->voucher_type) === VoucherType::Receipt->value)
            ->sum('amount'));
        $payments = $this->round((float) $vouchers
            ->filter(fn (Voucher $voucher): bool => $this->enumValue($voucher->voucher_type) === VoucherType::Payment->value)
            ->sum('amount'));

        return [
            'receipts' => $receipts,
            'payments' => $payments,
            'net_cash_flow' => $this->round($receipts - $payments),
            'count' => $vouchers->count(),
        ];
    }

    private function invoiceRow(string $type, mixed $date, ?string $number, ?string $party, array $amounts): array
    {
        return [
            'date' => $date,
            'type' => $type,
            'number' => $number ?: '-',
            'party' => $party ?: '-',
            'net' => $this->round((float) $amounts['net']),
            'vat' => $this->round((float) $amounts['vat']),
            'gross' => $this->round((float) $amounts['gross']),
        ];
    }

    private function salesInvoiceAmounts(SalesInvoice $invoice, ?int $taxRateId): array
    {
        if (! $taxRateId) {
            return [
                'net' => $this->round((float) $invoice->subtotal - (float) $invoice->discount),
                'vat' => (float) $invoice->vat_total,
                'gross' => (float) $invoice->total,
            ];
        }

        return $this->lineAmounts($invoice->items, $taxRateId);
    }

    private function purchaseInvoiceAmounts(PurchaseInvoice $invoice, ?int $taxRateId): array
    {
        if (! $taxRateId) {
            return [
                'net' => $this->round((float) $invoice->subtotal - (float) $invoice->discount),
                'vat' => (float) $invoice->vat_total,
                'gross' => (float) $invoice->total,
            ];
        }

        return $this->lineAmounts($invoice->items, $taxRateId);
    }

    private function salesReturnAmounts(SalesReturn $return, ?int $taxRateId): array
    {
        if (! $taxRateId) {
            return ['net' => (float) $return->subtotal, 'vat' => (float) $return->vat_total, 'gross' => (float) $return->total];
        }

        return $this->lineAmounts($return->items, $taxRateId);
    }

    private function purchaseReturnAmounts(PurchaseReturn $return, ?int $taxRateId): array
    {
        if (! $taxRateId) {
            return ['net' => (float) $return->subtotal, 'vat' => (float) $return->vat_total, 'gross' => (float) $return->total];
        }

        return $this->lineAmounts($return->items, $taxRateId);
    }

    private function lineAmounts(Collection $items, int $taxRateId): array
    {
        $matched = $items->filter(fn (SalesInvoiceItem|PurchaseInvoiceItem|SalesReturnItem|PurchaseReturnItem $item): bool => (int) $item->tax_rate_id === $taxRateId);
        $net = $matched->sum(fn ($item): float => $this->round((float) $item->qty * (float) $item->rate));
        $vat = $matched->sum(fn ($item): float => (float) $item->vat_amount);

        return ['net' => $net, 'vat' => $vat, 'gross' => $this->round($net + $vat)];
    }

    private function summarise(Collection $rows): array
    {
        return [
            'count' => $rows->count(),
            'net' => $this->round((float) $rows->sum('net')),
            'vat' => $this->round((float) $rows->sum('vat')),
            'gross' => $this->round((float) $rows->sum('gross')),
        ];
    }

    private function emptySummary(): array
    {
        return ['count' => 0, 'net' => 0.0, 'vat' => 0.0, 'gross' => 0.0];
    }

    private function chartData(array ...$summaries): array
    {
        $labels = ['Sales', 'Purchases', 'Expenses', 'Income', 'Sales Returns', 'Purchase Returns'];

        return collect($summaries)
            ->values()
            ->map(fn (array $summary, int $index): array => [
                'label' => $labels[$index],
                'vat' => $this->round((float) $summary['vat']),
                'net' => $this->round((float) $summary['net']),
            ])
            ->all();
    }

    private function round(float $amount): float
    {
        return round($amount, 2);
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
    }
}
