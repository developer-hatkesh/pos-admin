<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\BankTransactionType;
use App\Enums\ExpenseStatus;
use App\Enums\InvoiceStatus;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Expense;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DailySummaryReportService
{
    public function report(?int $companyId, Carbon|string $startDate, Carbon|string $endDate): array
    {
        $companyId ??= app(CurrentCompany::class)->id();

        abort_unless($companyId !== null, 403);

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();
        $invoices = $this->salesInvoices($companyId, $start, $end);
        $payments = $this->paymentTotals($invoices);
        $expenseTotals = $this->expenseTotals($companyId, $start, $end);
        $bankBalances = $this->bankBalances($companyId, $end);

        return [
            'company' => Company::query()->find($companyId),
            'start_date' => $start,
            'end_date' => $end,
            'generated_at' => now(),
            'sales' => [
                'total' => round((float) $invoices->sum('total'), 2),
                'cash' => $payments['cash'],
                'credit' => $payments['credit'],
                'bank_transfer' => $payments['bank_transfer'],
                'card_payment' => $payments['card_payment'],
                'other_payment' => $payments['other_payment'],
            ],
            'outgoings' => [
                'expenses' => $expenseTotals['expenses'],
                'wages' => $expenseTotals['wages'],
            ],
            'stock' => [
                'perfume_qty_sold' => $this->quantitySold($companyId, $start, $end),
            ],
            'bank_balances' => $bankBalances,
        ];
    }

    private function salesInvoices(int $companyId, Carbon $start, Carbon $end): Collection
    {
        return SalesInvoice::withoutGlobalScopes()
            ->with('paymentMethod')
            ->where('company_id', $companyId)
            ->whereBetween('invoice_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('status', [
                InvoiceStatus::Posted->value,
                InvoiceStatus::Paid->value,
                InvoiceStatus::Partial->value,
            ])
            ->get();
    }

    private function paymentTotals(Collection $invoices): array
    {
        $totals = [
            'cash' => 0.0,
            'credit' => 0.0,
            'bank_transfer' => 0.0,
            'card_payment' => 0.0,
            'other_payment' => 0.0,
        ];

        foreach ($invoices as $invoice) {
            $parsed = $this->parsePaymentNote((string) $invoice->payment_note);

            if ($parsed !== []) {
                foreach ($parsed as $label => $amount) {
                    $totals[$this->paymentBucket($label)] += $amount;
                }

                continue;
            }

            if ($invoice->status === InvoiceStatus::Posted) {
                $totals['credit'] += (float) $invoice->total;

                continue;
            }

            $totals[$this->paymentBucket($invoice->paymentMethod?->name)] += (float) $invoice->total;
        }

        return array_map(fn (float $amount): float => round($amount, 2), $totals);
    }

    private function parsePaymentNote(string $note): array
    {
        if (blank($note)) {
            return [];
        }

        $payments = [];

        foreach (preg_split('/\R/', $note) ?: [] as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$label, $amount] = array_map('trim', explode(':', $line, 2));
            $numericAmount = (float) preg_replace('/[^0-9.\-]/', '', $amount);

            if ($label !== '' && $numericAmount > 0) {
                $payments[$label] = ($payments[$label] ?? 0.0) + $numericAmount;
            }
        }

        return $payments;
    }

    private function paymentBucket(?string $label): string
    {
        $label = Str::lower((string) $label);

        return match (true) {
            Str::contains($label, ['credit', 'due', 'cod', 'c.o.d']) => 'credit',
            Str::contains($label, ['card', 'visa', 'master']) => 'card_payment',
            Str::contains($label, ['bank', 'transfer', 'bacs', 'wire']) => 'bank_transfer',
            Str::contains($label, ['cash']) => 'cash',
            default => 'other_payment',
        };
    }

    private function expenseTotals(int $companyId, Carbon $start, Carbon $end): array
    {
        $rows = Expense::withoutGlobalScopes()
            ->with('category')
            ->where('company_id', $companyId)
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('status', [ExpenseStatus::Posted->value, ExpenseStatus::Paid->value])
            ->get();

        $wages = 0.0;
        $expenses = 0.0;

        foreach ($rows as $expense) {
            $amount = (float) $expense->grand_total_amount;
            $categoryName = Str::lower((string) $expense->category?->category_name);

            if (Str::contains($categoryName, ['wage', 'salary', 'payroll'])) {
                $wages += $amount;
            } else {
                $expenses += $amount;
            }
        }

        return [
            'expenses' => round($expenses, 2),
            'wages' => round($wages, 2),
        ];
    }

    private function quantitySold(int $companyId, Carbon $start, Carbon $end): float
    {
        return round((float) SalesInvoiceItem::query()
            ->join('sales_invoices', 'sales_invoices.id', '=', 'sales_invoice_items.invoice_id')
            ->where('sales_invoices.company_id', $companyId)
            ->whereBetween('sales_invoices.invoice_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('sales_invoices.status', [
                InvoiceStatus::Posted->value,
                InvoiceStatus::Paid->value,
                InvoiceStatus::Partial->value,
            ])
            ->whereNotNull('sales_invoice_items.product_item_id')
            ->sum('sales_invoice_items.qty'), 3);
    }

    private function bankBalances(int $companyId, Carbon $end): array
    {
        return BankAccount::withoutGlobalScopes()
            ->with('ledger')
            ->where('company_id', $companyId)
            ->orderBy('bank_name')
            ->orderBy('account_name')
            ->get()
            ->map(fn (BankAccount $account): array => [
                'name' => trim(collect([$account->bank_name, $account->account_name])->filter()->implode(' - ')),
                'amount' => $this->bankBalance($account, $end),
            ])
            ->values()
            ->all();
    }

    private function bankBalance(BankAccount $account, Carbon $end): float
    {
        $transactions = $account->bankTransactions()
            ->where('transaction_date', '<=', $end->toDateString())
            ->selectRaw("COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE -amount END), 0) as movement", [BankTransactionType::Deposit->value])
            ->first();

        return round((float) $account->opening_balance + (float) ($transactions?->movement ?? 0), 2);
    }
}
