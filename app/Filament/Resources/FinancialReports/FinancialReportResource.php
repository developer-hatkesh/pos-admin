<?php

declare(strict_types=1);

namespace App\Filament\Resources\FinancialReports;

use App\Enums\LedgerType;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\FinancialReports\Pages\ListFinancialReports;
use App\Models\JournalLine;
use App\Models\Ledger;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class FinancialReportResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = Ledger::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 13;

    protected static ?string $navigationLabel = 'Financial Reports';

    protected static ?string $modelLabel = 'Financial Report Entry';

    protected static ?string $pluralModelLabel = 'Financial Reports';

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('financial_report_name')
                    ->label('Report')
                    ->state(fn (Ledger $record): string => self::reportName($record))
                    ->badge(),
                TextColumn::make('source')
                    ->label('Source')
                    ->state(fn (Ledger $record): string => self::source($record)),
                TextColumn::make('connected')
                    ->label('Connected')
                    ->state(fn (Ledger $record): string => self::connected($record)),
                TextColumn::make('nominal_code')->label('Code')->searchable()->sortable(),
                TextColumn::make('name')->label('Ledger')->searchable()->sortable(),
                TextColumn::make('debit_total')
                    ->label('Debit')
                    ->state(fn (Ledger $record): float => self::debitTotal($record))
                    ->formatStateUsing(fn (mixed $state): string => app_money($state))
                    ->color('success'),
                TextColumn::make('credit_total')
                    ->label('Credit')
                    ->state(fn (Ledger $record): float => self::creditTotal($record))
                    ->formatStateUsing(fn (mixed $state): string => app_money($state))
                    ->color('danger'),
                TextColumn::make('closing_balance')
                    ->label('Balance')
                    ->state(fn (Ledger $record): float => self::balance($record))
                    ->formatStateUsing(fn (mixed $state): string => app_money(abs((float) $state))),
            ])
            ->filters([
                SelectFilter::make('type')->options(LedgerType::class),
            ])
            ->defaultSort('nominal_code')
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function trialBalanceQuery(Builder $query): Builder
    {
        return $query;
    }

    public static function profitLossQuery(Builder $query): Builder
    {
        return $query->whereIn('type', [LedgerType::Income->value, LedgerType::Expense->value]);
    }

    public static function balanceSheetQuery(Builder $query): Builder
    {
        return $query->whereIn('type', [LedgerType::Asset->value, LedgerType::Liability->value, LedgerType::Equity->value]);
    }

    private static function reportName(Ledger $ledger): string
    {
        return match ($ledger->type) {
            LedgerType::Income, LedgerType::Expense => 'Profit & Loss',
            LedgerType::Asset, LedgerType::Liability, LedgerType::Equity => 'Balance Sheet',
            default => 'Trial Balance',
        };
    }

    private static function source(Ledger $ledger): string
    {
        return match ($ledger->type) {
            LedgerType::Income => 'Sales',
            LedgerType::Expense => 'Purchase, Expense',
            LedgerType::Asset => self::assetSource($ledger),
            LedgerType::Liability, LedgerType::Equity => 'GL',
            default => 'General Ledger',
        };
    }

    private static function connected(Ledger $ledger): string
    {
        return match ($ledger->type) {
            LedgerType::Income, LedgerType::Expense => 'Dashboard',
            LedgerType::Asset, LedgerType::Liability, LedgerType::Equity => 'Dashboard',
            default => 'P&L, Balance Sheet',
        };
    }

    private static function assetSource(Ledger $ledger): string
    {
        $name = strtolower($ledger->name);

        if (str_contains($name, 'stock')) {
            return 'Stock';
        }

        if (str_contains($name, 'bank')) {
            return 'Bank';
        }

        if (str_contains($name, 'cash')) {
            return 'Cash';
        }

        return 'GL';
    }

    private static function debitTotal(Ledger $ledger): float
    {
        return round((float) $ledger->opening_balance + self::sum($ledger, 'debit'), 2);
    }

    private static function creditTotal(Ledger $ledger): float
    {
        return round(self::sum($ledger, 'credit'), 2);
    }

    private static function balance(Ledger $ledger): float
    {
        return round(self::debitTotal($ledger) - self::creditTotal($ledger), 2);
    }

    private static function sum(Ledger $ledger, string $column): float
    {
        return (float) JournalLine::query()
            ->where('ledger_id', $ledger->id)
            ->sum($column);
    }

    public static function getPages(): array
    {
        return ['index' => ListFinancialReports::route('/')];
    }
}
