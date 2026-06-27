<?php

declare(strict_types=1);

namespace App\Filament\Resources\BankLedgerReports;

use App\Enums\Status;
use App\Filament\Resources\BankLedgerReports\Pages\BankLedgerDetailPage;
use App\Filament\Resources\BankLedgerReports\Pages\BankLedgerReportPage;
use App\Models\BankAccount;
use App\Services\Reports\BankLedgerReportService;
use App\Services\Reports\CurrencyService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class BankLedgerReportResource extends Resource
{
    protected static ?string $model = BankAccount::class;

    protected static ?string $slug = 'reports/bank-ledger';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 12;

    protected static ?string $navigationLabel = 'Bank Ledger';

    protected static ?string $modelLabel = 'Bank Ledger';

    protected static ?string $pluralModelLabel = 'Bank Ledger';

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return app(BankLedgerReportService::class)->query();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bank_name')->label('Bank Name')->searchable()->sortable(),
                TextColumn::make('account_name')->label('Account Name')->searchable()->sortable(),
                TextColumn::make('account_number')->label('Account Number')->searchable(),
                TextColumn::make('sort_code')->label('Sort Code / IFSC / Bank Code')->searchable(),
                TextColumn::make('opening_balance_report')
                    ->label('Opening Balance')
                    ->state(fn (BankAccount $record, mixed $livewire): string => self::summary($record, $livewire)['opening_formatted']),
                TextColumn::make('total_debit_report')
                    ->label('Total Debit')
                    ->state(fn (BankAccount $record, mixed $livewire): string => CurrencyService::format(self::summary($record, $livewire)['debit'])),
                TextColumn::make('total_credit_report')
                    ->label('Total Credit')
                    ->state(fn (BankAccount $record, mixed $livewire): string => CurrencyService::format(self::summary($record, $livewire)['credit'])),
                TextColumn::make('closing_balance_report')
                    ->label('Closing Balance')
                    ->state(fn (BankAccount $record, mixed $livewire): string => self::summary($record, $livewire)['closing_formatted']),
                TextColumn::make('dr_cr_report')
                    ->label('Dr/Cr')
                    ->state(fn (BankAccount $record, mixed $livewire): string => self::summary($record, $livewire)['dr_cr']),
                TextColumn::make('last_transaction_date')
                    ->label('Last Transaction Date')
                    ->state(fn (BankAccount $record, mixed $livewire): ?string => self::lastTransactionDate($record, $livewire))
                    ->date(),
                TextColumn::make('status')->badge()->sortable(),
            ])
            ->filters([
                Filter::make('report_filters')
                    ->schema([
                        DatePicker::make('from')->label('From Date'),
                        DatePicker::make('until')->label('To Date'),
                        Select::make('balance_type')->label('Balance Type')->options([
                            'all' => 'All',
                            'debit' => 'Debit Balance',
                            'credit' => 'Credit Balance',
                            'zero' => 'Zero Balance',
                        ])->default('all'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => self::applyBalanceFilter($query, $data)),
                SelectFilter::make('status')->options(Status::class),
            ])
            ->defaultSort('bank_name')
            ->recordActions([
                Action::make('showDetails')
                    ->label('Show Details')
                    ->icon(Heroicon::Eye)
                    ->url(fn (BankAccount $record): string => static::getUrl('view', ['record' => $record])),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => BankLedgerReportPage::route('/'),
            'view' => BankLedgerDetailPage::route('/{record}'),
        ];
    }

    private static function summary(BankAccount $record, mixed $livewire): array
    {
        [$from, $to] = self::dateFilters($livewire);

        return app(BankLedgerReportService::class)->summary($record, $from, $to);
    }

    public static function dateFilters(mixed $livewire): array
    {
        $filters = $livewire->tableFilters ?? [];
        $range = $filters['report_filters'] ?? [];

        return [$range['from'] ?? null, $range['until'] ?? null];
    }

    private static function lastTransactionDate(BankAccount $record, mixed $livewire): ?string
    {
        [$from, $to] = self::dateFilters($livewire);

        return app(BankLedgerReportService::class)->lastTransactionDate($record, $from, $to);
    }

    private static function applyBalanceFilter(Builder $query, array $data): Builder
    {
        $type = $data['balance_type'] ?? 'all';

        if ($type === 'all' || blank($type)) {
            return $query;
        }

        [$expression, $bindings] = app(BankLedgerReportService::class)->closingBalanceSql($data['from'] ?? null, $data['until'] ?? null);

        return match ($type) {
            'debit' => $query->whereRaw($expression.' > 0', $bindings),
            'credit' => $query->whereRaw($expression.' < 0', $bindings),
            'zero' => $query->whereRaw('ROUND(('.$expression.'), 2) = 0', $bindings),
            default => $query,
        };
    }
}
