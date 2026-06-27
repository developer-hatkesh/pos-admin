<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\JournalLine;
use App\Models\Ledger;
use App\Models\ProductItem;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\Supplier;
use App\Support\Purchases\PurchaseReportSql;
use App\Support\CurrentCompany;
use App\Support\Inventory\StockReportSql;
use App\Support\Sales\SalesReportSql;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use UnitEnum;

class Dashboard extends BaseDashboard
{
    protected static string $routePath = '/';

    protected static ?string $title = 'Dashboard';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.dashboard';

    protected Width|string|null $maxContentWidth = Width::Full;

    public function getWidgets(): array
    {
        return [];
    }

    public function getViewData(): array
    {
        return [
            'metrics' => $this->metrics(),
            'weeklySalesPurchases' => $this->weeklySalesPurchases(),
            'topCategories' => $this->topCategoriesForWeek(),
            'topProducts' => $this->topProductsForWeek(),
            'topCustomers' => $this->topCustomersForWeek(),
            'recentSales' => $this->recentSales(),
            'stockAlerts' => $this->stockAlerts(),
        ];
    }

    private function metrics(): array
    {
        $today = now()->toDateString();
        $companyId = app(CurrentCompany::class)->id();

        $sales = SalesInvoice::query()->whereIn('status', ['posted', 'paid', 'partial']);
        $todaySales = (clone $sales)->whereDate('invoice_date', $today);
        $todayPurchase = PurchaseInvoice::query()
            ->whereIn('status', ['posted', 'paid', 'partial'])
            ->whereDate('invoice_date', $today);

        $items = ProductItem::query();
        $customers = Customer::query();
        $suppliers = Supplier::query();

        return [
            [
                'label' => 'Total Sales',
                'value' => $this->money((float) $sales->sum('total')),
                'icon' => 'heroicon-o-chart-bar-square',
                'tone' => 'blue',
            ],
            [
                'label' => "Today's Sales",
                'value' => $this->money((float) $todaySales->sum('total')),
                'icon' => 'heroicon-o-shopping-cart',
                'tone' => 'green',
            ],
            [
                'label' => "Today's Purchase",
                'value' => $this->money((float) $todayPurchase->sum('total')),
                'icon' => 'heroicon-o-clipboard-document-list',
                'tone' => 'amber',
            ],
            [
                'label' => 'Customer Due',
                'value' => $this->money($this->customerDue()),
                'icon' => 'heroicon-o-user-group',
                'tone' => 'red',
            ],
            [
                'label' => 'Supplier Due',
                'value' => $this->money($this->supplierDue()),
                'icon' => 'heroicon-o-truck',
                'tone' => 'amber',
            ],
            [
                'label' => 'Cash Balance',
                'value' => $this->money($this->cashBalance()),
                'icon' => 'heroicon-o-banknotes',
                'tone' => 'violet',
            ],
            [
                'label' => 'Total Items',
                'value' => number_format((int) $items->count()),
                'icon' => 'heroicon-o-cube',
                'tone' => 'violet',
            ],
            [
                'label' => 'Low Stock Items',
                'value' => number_format((clone $items)
                    ->where('stock_enabled', true)
                    ->whereNotNull('stock_alert_qty')
                    ->get()
                    ->filter(fn (ProductItem $item): bool => $item->current_stock <= (float) $item->stock_alert_qty)
                    ->count()),
                'icon' => 'heroicon-o-exclamation-triangle',
                'tone' => 'amber',
            ],
            [
                'label' => 'Customers',
                'value' => number_format((int) $customers->count()),
                'icon' => 'heroicon-o-user-group',
                'tone' => 'blue',
            ],
            [
                'label' => 'Suppliers',
                'value' => number_format((int) $suppliers->count()),
                'icon' => 'heroicon-o-truck',
                'tone' => 'slate',
            ],
            [
                'label' => 'VAT Due',
                'value' => $this->money($this->vatDue($companyId)),
                'icon' => 'heroicon-o-receipt-percent',
                'tone' => 'red',
            ],
            [
                'label' => 'Bank Balance',
                'value' => $this->money($this->bankBalance()),
                'icon' => 'heroicon-o-banknotes',
                'tone' => 'green',
            ],
        ];
    }

    private function vatDue(?int $companyId): float
    {
        if ($companyId === null) {
            return 0;
        }

        $vatOutput = Ledger::query()->withoutGlobalScope('company')->where('company_id', $companyId)->where('nominal_code', '2201')->first();
        $vatInput = Ledger::query()->withoutGlobalScope('company')->where('company_id', $companyId)->where('nominal_code', '2202')->first();

        if (! $vatOutput || ! $vatInput) {
            return 0;
        }

        $output = JournalLine::query()->where('ledger_id', $vatOutput->id)->sum('credit') - JournalLine::query()->where('ledger_id', $vatOutput->id)->sum('debit');
        $input = JournalLine::query()->where('ledger_id', $vatInput->id)->sum('debit') - JournalLine::query()->where('ledger_id', $vatInput->id)->sum('credit');

        return round((float) $output - (float) $input, 2);
    }

    private function customerDue(): float
    {
        $row = Customer::query()
            ->selectRaw('COALESCE(SUM('.SalesReportSql::outstandingSql().'), 0) as amount')
            ->first();

        return round((float) $row->amount, 2);
    }

    private function supplierDue(): float
    {
        $row = Supplier::query()
            ->selectRaw('COALESCE(SUM('.PurchaseReportSql::outstandingSql().'), 0) as amount')
            ->first();

        return round((float) $row->amount, 2);
    }

    private function cashBalance(): float
    {
        return $this->accountBalance(fn (Builder $query): Builder => $query
            ->where(function (Builder $query): void {
                $query
                    ->where('account_name', 'like', '%cash%')
                    ->orWhere('bank_name', 'like', '%cash%')
                    ->orWhereHas('ledger', fn (Builder $query): Builder => $query
                        ->where('nominal_code', '1000')
                        ->orWhere('name', 'like', '%cash%'));
            })
            ->where('account_name', 'not like', '%petty%')
            ->where(function (Builder $query): void {
                $query->whereNull('bank_name')->orWhere('bank_name', 'not like', '%petty%');
            })
            ->whereDoesntHave('ledger', fn (Builder $query): Builder => $query
                ->where('nominal_code', '1010')
                ->orWhere('name', 'like', '%petty%')));
    }

    private function bankBalance(): float
    {
        return $this->accountBalance(fn (Builder $query): Builder => $query
            ->where(function (Builder $query): void {
                $query
                    ->where(function (Builder $query): void {
                        $query
                            ->where('account_name', 'not like', '%cash%')
                            ->where(function (Builder $query): void {
                                $query->whereNull('bank_name')->orWhere('bank_name', 'not like', '%cash%');
                            });
                    })
                    ->orWhere('account_name', 'like', '%bank%')
                    ->orWhere('bank_name', 'like', '%bank%')
                    ->orWhereHas('ledger', fn (Builder $query): Builder => $query
                        ->where('nominal_code', 'like', '11%')
                        ->orWhere('nominal_code', '1200')
                        ->orWhere('name', 'like', '%bank%'));
            })
            ->where('account_name', 'not like', '%petty%')
            ->where(function (Builder $query): void {
                $query->whereNull('bank_name')->orWhere('bank_name', 'not like', '%petty%');
            })
            ->whereDoesntHave('ledger', fn (Builder $query): Builder => $query
                ->where('nominal_code', '1010')
                ->orWhere('nominal_code', '1000')
                ->orWhere('name', 'like', '%cash%')));
    }

    private function accountBalance(callable $accountFilter): float
    {
        $accounts = BankAccount::query()
            ->withSum(['bankTransactions as deposits_total' => fn (Builder $query): Builder => $query->where('type', 'deposit')], 'amount')
            ->withSum(['bankTransactions as withdrawals_total' => fn (Builder $query): Builder => $query->where('type', 'withdrawal')], 'amount')
            ->where($accountFilter)
            ->get();

        return round((float) $accounts->sum(
            fn (BankAccount $account): float => (float) $account->opening_balance
                + (float) $account->deposits_total
                - (float) $account->withdrawals_total
        ), 2);
    }

    private function weeklySalesPurchases(): array
    {
        [$start, $end] = $this->currentWeekRange();

        $days = collect(range(0, 6))->map(fn (int $offset): Carbon => $start->copy()->addDays($offset));

        $rows = $days->map(function (Carbon $date): array {
            $sales = SalesInvoice::query()
                ->whereIn('status', ['posted', 'paid', 'partial'])
                ->whereDate('invoice_date', $date->toDateString())
                ->sum('total');

            $purchases = PurchaseInvoice::query()
                ->whereIn('status', ['posted', 'paid', 'partial'])
                ->whereDate('invoice_date', $date->toDateString())
                ->sum('total');

            return [
                'date' => $date->toDateString(),
                'label' => $date->format('D d'),
                'sales' => round((float) $sales, 2),
                'purchases' => round((float) $purchases, 2),
            ];
        })->all();

        $maxAmount = max(1, collect($rows)->flatMap(fn (array $row): array => [$row['sales'], $row['purchases']])->max());

        return collect($rows)
            ->map(function (array $row) use ($maxAmount): array {
                $row['salesHeight'] = max(3, (int) round(($row['sales'] / $maxAmount) * 100));
                $row['purchasesHeight'] = max(3, (int) round(($row['purchases'] / $maxAmount) * 100));

                return $row;
            })
            ->all();
    }

    private function topCategoriesForWeek(): array
    {
        [$start, $end] = $this->currentWeekRange();
        $categoryLabelSql = "COALESCE(categories.name, 'Uncategorised')";

        $rows = SalesInvoiceItem::query()
            ->join('sales_invoices', 'sales_invoice_items.invoice_id', '=', 'sales_invoices.id')
            ->leftJoin('product_items', 'sales_invoice_items.product_item_id', '=', 'product_items.id')
            ->leftJoin('categories', 'product_items.category_id', '=', 'categories.id')
            ->whereIn('sales_invoices.status', ['posted', 'paid', 'partial'])
            ->whereBetween('sales_invoices.invoice_date', [$start->toDateString(), $end->toDateString()])
            ->when(app(CurrentCompany::class)->id(), fn (Builder $query, int $companyId): Builder => $query->where('sales_invoices.company_id', $companyId))
            ->selectRaw("{$categoryLabelSql} as label, COALESCE(SUM(sales_invoice_items.line_total), 0) as amount")
            ->groupByRaw($categoryLabelSql)
            ->orderByDesc('amount')
            ->limit(5)
            ->get()
            ->map(fn ($row): array => [
                'label' => (string) $row->label,
                'value' => round((float) $row->amount, 2),
            ])
            ->all();

        return $this->pieData($rows, ['#2563eb', '#16a34a', '#d97706', '#dc2626', '#7c3aed']);
    }

    private function topProductsForWeek(): array
    {
        [$start, $end] = $this->currentWeekRange();

        return SalesInvoiceItem::query()
            ->join('sales_invoices', 'sales_invoice_items.invoice_id', '=', 'sales_invoices.id')
            ->leftJoin('product_items', 'sales_invoice_items.product_item_id', '=', 'product_items.id')
            ->whereIn('sales_invoices.status', ['posted', 'paid', 'partial'])
            ->whereBetween('sales_invoices.invoice_date', [$start->toDateString(), $end->toDateString()])
            ->when(app(CurrentCompany::class)->id(), fn (Builder $query, int $companyId): Builder => $query->where('sales_invoices.company_id', $companyId))
            ->selectRaw("COALESCE(product_items.name, sales_invoice_items.description, 'Unknown product') as name")
            ->selectRaw('COALESCE(SUM(sales_invoice_items.qty), 0) as quantity')
            ->selectRaw('COALESCE(SUM(sales_invoice_items.line_total), 0) as amount')
            ->groupBy('name')
            ->orderByDesc('quantity')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => [
                'name' => (string) $row->name,
                'quantity' => (float) $row->quantity,
                'amount' => round((float) $row->amount, 2),
            ])
            ->all();
    }

    private function topCustomersForWeek(): array
    {
        [$start, $end] = $this->currentWeekRange();
        $customerLabelSql = "COALESCE(customers.name, 'Walk-in Customer')";

        $rows = SalesInvoice::query()
            ->leftJoin('customers', 'sales_invoices.customer_id', '=', 'customers.id')
            ->whereIn('sales_invoices.status', ['posted', 'paid', 'partial'])
            ->whereBetween('sales_invoices.invoice_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw("{$customerLabelSql} as label, COALESCE(SUM(sales_invoices.total), 0) as amount")
            ->groupByRaw($customerLabelSql)
            ->orderByDesc('amount')
            ->limit(5)
            ->get()
            ->map(fn ($row): array => [
                'label' => (string) $row->label,
                'value' => round((float) $row->amount, 2),
            ])
            ->all();

        return $this->pieData($rows, ['#0891b2', '#65a30d', '#ea580c', '#be123c', '#4f46e5']);
    }

    private function recentSales(): array
    {
        return SalesInvoice::query()
            ->with('customer:id,name')
            ->latest('invoice_date')
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (SalesInvoice $invoice): array => [
                'invoiceNo' => $invoice->invoice_no,
                'date' => $invoice->invoice_date?->format('d M Y') ?? '-',
                'customer' => $invoice->customer?->name ?? 'Walk-in Customer',
                'total' => (float) $invoice->total,
                'status' => $invoice->status?->value ?? (string) $invoice->status,
            ])
            ->all();
    }

    private function stockAlerts(): array
    {
        $currentStockSql = StockReportSql::currentStockSql();

        return ProductItem::query()
            ->select('product_items.id', 'product_items.name', 'product_items.stock_alert_qty')
            ->selectRaw("{$currentStockSql} as current_stock")
            ->where('stock_enabled', true)
            ->whereNotNull('stock_alert_qty')
            ->orderBy('name')
            ->get()
            ->filter(fn (ProductItem $item): bool => (float) $item->current_stock <= (float) $item->stock_alert_qty)
            ->take(10)
            ->map(fn (ProductItem $item): array => [
                'name' => $item->name,
                'currentStock' => (float) $item->current_stock,
                'alertQty' => (float) $item->stock_alert_qty,
            ])
            ->values()
            ->all();
    }

    private function pieData(array $rows, array $colors): array
    {
        $total = max(0, array_sum(array_column($rows, 'value')));
        $position = 0.0;

        $slices = collect($rows)->values()->map(function (array $row, int $index) use ($total, $colors, &$position): array {
            $percentage = $total > 0 ? ((float) $row['value'] / $total) * 100 : 0;
            $start = $position;
            $position += $percentage;

            return [
                'label' => $row['label'],
                'value' => (float) $row['value'],
                'percentage' => round($percentage, 1),
                'color' => $colors[$index % count($colors)],
                'start' => $start,
                'end' => $position,
            ];
        })->all();

        $gradient = collect($slices)
            ->map(fn (array $slice): string => sprintf('%s %.2f%% %.2f%%', $slice['color'], $slice['start'], $slice['end']))
            ->implode(', ');

        return [
            'slices' => $slices,
            'total' => round((float) $total, 2),
            'gradient' => $gradient ?: '#e5e7eb 0% 100%',
        ];
    }

    private function currentWeekRange(): array
    {
        return [
            now()->startOfWeek()->startOfDay(),
            now()->endOfWeek()->endOfDay(),
        ];
    }

    private function money(float $amount): string
    {
        return app_money($amount);

    }
}
