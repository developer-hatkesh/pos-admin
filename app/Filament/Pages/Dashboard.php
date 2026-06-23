<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Customer;
use App\Models\JournalLine;
use App\Models\Ledger;
use App\Models\ProductItem;
use App\Models\SalesInvoice;
use App\Models\Supplier;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
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
        $companyId = auth()->user()?->company_id;

        $sales = SalesInvoice::query()->whereIn('status', ['posted', 'paid', 'partial']);
        $todaySales = (clone $sales)->whereDate('invoice_date', $today);

        $items = ProductItem::query();
        $customers = Customer::query();
        $suppliers = Supplier::query();

        $bankOpening = BankAccount::query()->sum('opening_balance');
        $deposits = BankTransaction::query()->where('type', 'deposit')->sum('amount');
        $withdrawals = BankTransaction::query()->where('type', 'withdrawal')->sum('amount');

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
                'value' => $this->money((float) $bankOpening + (float) $deposits - (float) $withdrawals),
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

    private function money(float $amount): string
    {
        return app_money($amount);

    }
}
