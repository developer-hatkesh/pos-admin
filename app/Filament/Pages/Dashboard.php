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
use App\Models\Supplier;
use App\Support\Purchases\PurchaseReportSql;
use App\Support\CurrentCompany;
use App\Support\Sales\SalesReportSql;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
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

    private function money(float $amount): string
    {
        return app_money($amount);

    }
}
