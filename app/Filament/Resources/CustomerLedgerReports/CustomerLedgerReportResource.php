<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomerLedgerReports;

use App\Filament\Resources\CustomerLedgerReports\Pages\CustomerLedgerDetailPage;
use App\Filament\Resources\CustomerLedgerReports\Pages\CustomerLedgerReportPage;
use App\Models\Customer;
use App\Models\JournalLine;
use App\Services\Reports\CurrencyService;
use App\Services\Reports\CustomerLedgerReportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class CustomerLedgerReportResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $slug = 'reports/customer-ledger';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?string $navigationParentItem = 'Ledger Reports';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Customer Ledger';

    protected static ?string $modelLabel = 'Customer Ledger';

    protected static ?string $pluralModelLabel = 'Customer Ledger';

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return app(CustomerLedgerReportService::class)->query();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Customer Name')->searchable()->sortable(),
                TextColumn::make('customer_code')->label('Customer Code')->searchable()->sortable(),
                TextColumn::make('phone')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('opening_balance_report')
                    ->label('Opening Balance')
                    ->state(fn (Customer $record, mixed $livewire): string => self::summary($record, $livewire)['opening_formatted']),
                TextColumn::make('total_debit_report')
                    ->label('Total Debit')
                    ->state(fn (Customer $record, mixed $livewire): string => CurrencyService::format(self::summary($record, $livewire)['debit'])),
                TextColumn::make('total_credit_report')
                    ->label('Total Credit')
                    ->state(fn (Customer $record, mixed $livewire): string => CurrencyService::format(self::summary($record, $livewire)['credit'])),
                TextColumn::make('closing_balance_report')
                    ->label('Closing Balance')
                    ->state(fn (Customer $record, mixed $livewire): string => self::summary($record, $livewire)['closing_formatted']),
                TextColumn::make('invoice_outstanding_report')
                    ->label('Invoice Outstanding')
                    ->state(fn (Customer $record, mixed $livewire): string => self::summary($record, $livewire)['invoice_outstanding_formatted']),
                TextColumn::make('unallocated_credit_report')
                    ->label('Unallocated Credit')
                    ->state(fn (Customer $record, mixed $livewire): string => self::summary($record, $livewire)['unallocated_credit_formatted']),
                TextColumn::make('dr_cr_report')
                    ->label('Dr/Cr')
                    ->state(fn (Customer $record, mixed $livewire): string => self::summary($record, $livewire)['dr_cr']),
                TextColumn::make('last_transaction_date')
                    ->label('Last Transaction Date')
                    ->state(fn (Customer $record): ?string => self::lastTransactionDate($record))
                    ->date(),
                TextColumn::make('status')->badge()->sortable(),
            ])
            ->defaultSort('name')
            ->recordActions([
                Action::make('showDetails')
                    ->label('Show Details')
                    ->icon(Heroicon::Eye)
                    ->url(fn (Customer $record): string => static::getUrl('view', ['record' => $record])),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => CustomerLedgerReportPage::route('/'),
            'view' => CustomerLedgerDetailPage::route('/{record}'),
        ];
    }

    private static function summary(Customer $record, mixed $livewire): array
    {
        [$from, $to] = self::dateFilters($livewire);

        return app(CustomerLedgerReportService::class)->summary($record, $from, $to);
    }

    public static function dateFilters(mixed $livewire): array
    {
        if (method_exists($livewire, 'reportStartDate') && method_exists($livewire, 'reportEndDate')) {
            return [$livewire->reportStartDate(), $livewire->reportEndDate()];
        }

        return [request('start_date') ?: request('from'), request('end_date') ?: request('to')];
    }

    private static function lastTransactionDate(Customer $record): ?string
    {
        if ($record->ledger_id === null) {
            return null;
        }

        return JournalLine::query()
            ->where('ledger_id', $record->ledger_id)
            ->whereHas('journalEntry', fn (Builder $query): Builder => $query->where('company_id', $record->company_id))
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_id')
            ->max('journal_entries.entry_date');
    }

    public static function applyPermanentFilters(Builder $query, mixed $livewire): Builder
    {
        if (($livewire->status ?? 'all') !== 'all') {
            $query->where('status', $livewire->status);
        }

        $type = $livewire->balanceType ?? 'all';

        if ($type === 'all' || blank($type)) {
            return $query;
        }

        [$fromDate, $toDate] = self::dateFilters($livewire);

        [$expression, $bindings] = self::closingBalanceSql($fromDate, $toDate);

        return match ($type) {
            'debit' => $query->whereRaw($expression.' > 0', $bindings),
            'credit' => $query->whereRaw($expression.' < 0', $bindings),
            'zero' => $query->whereRaw('ROUND(('.$expression.'), 2) = 0', $bindings),
            default => $query,
        };
    }

    private static function closingBalanceSql(?string $fromDate, ?string $toDate): array
    {
        $invoiceDateSql = '';
        $voucherDateSql = '';
        $returnDateSql = '';
        $bindings = [];

        if (filled($toDate)) {
            $invoiceDateSql = ' AND sales_invoices.invoice_date <= ?';
            $voucherDateSql = ' AND vouchers.voucher_date <= ?';
            $returnDateSql = ' AND sales_returns.return_date <= ?';
        }

        $openingSql = "CASE
            WHEN customers.balance_type = 'Cr' THEN -ABS(customers.opening_balance)
            ELSE ABS(customers.opening_balance)
        END";

        $invoiceSql = "COALESCE((SELECT SUM(sales_invoices.total)
            FROM sales_invoices
            WHERE sales_invoices.customer_id = customers.id
              AND sales_invoices.status IN ('posted', 'partial', 'paid'){$invoiceDateSql}), 0)";
        $receiptSql = "COALESCE((SELECT SUM(vouchers.amount)
            FROM vouchers
            WHERE vouchers.customer_id = customers.id
              AND vouchers.voucher_type = 'receipt'
              AND vouchers.status = 'posted'{$voucherDateSql}), 0)";
        $returnSql = "COALESCE((SELECT SUM(sales_returns.total)
            FROM sales_returns
            WHERE sales_returns.customer_id = customers.id
              AND sales_returns.status = 'posted'{$returnDateSql}), 0)";

        if (filled($toDate)) {
            $bindings = [$toDate, $toDate, $toDate];
        }

        return ['('.$openingSql.' + '.$invoiceSql.' - '.$receiptSql.' - '.$returnSql.')', $bindings];
    }
}
