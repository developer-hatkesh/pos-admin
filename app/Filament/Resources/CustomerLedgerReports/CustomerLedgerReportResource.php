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

        return $query->whereHas('ledger', function (Builder $ledgerQuery) use ($fromDate, $toDate, $type): Builder {
            [$expression, $bindings] = self::closingBalanceSql($fromDate, $toDate);

            return match ($type) {
                'debit' => $ledgerQuery->whereRaw($expression.' > 0', $bindings),
                'credit' => $ledgerQuery->whereRaw($expression.' < 0', $bindings),
                'zero' => $ledgerQuery->whereRaw('ROUND(('.$expression.'), 2) = 0', $bindings),
                default => $ledgerQuery,
            };
        });
    }

    private static function closingBalanceSql(?string $fromDate, ?string $toDate): array
    {
        $dateSql = '';
        $bindings = [];

        if (filled($toDate)) {
            $dateSql .= ' AND journal_entries.entry_date <= ?';
            $bindings[] = $toDate;
        }

        $openingSql = "CASE
            WHEN ledgers.balance_type = 'Cr' THEN -ABS(ledgers.opening_balance)
            WHEN ledgers.balance_type = 'Dr' THEN ABS(ledgers.opening_balance)
            WHEN ledgers.type IN ('liability', 'income', 'equity') THEN -ABS(ledgers.opening_balance)
            ELSE ABS(ledgers.opening_balance)
        END";

        $movementSql = "COALESCE((
            SELECT SUM(journal_lines.debit - journal_lines.credit)
            FROM journal_lines
            INNER JOIN journal_entries ON journal_entries.id = journal_lines.journal_id
            WHERE journal_lines.ledger_id = ledgers.id{$dateSql}
        ), 0)";

        return ['('.$openingSql.' + '.$movementSql.')', $bindings];
    }
}
