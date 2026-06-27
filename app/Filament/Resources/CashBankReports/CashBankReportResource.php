<?php

declare(strict_types=1);

namespace App\Filament\Resources\CashBankReports;

use App\Enums\BankTransactionType;
use App\Enums\VoucherType;
use App\Filament\Resources\CashBankReports\Pages\ListCashBankReports;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Models\BankTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class CashBankReportResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = BankTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 12;

    protected static ?string $navigationLabel = 'Cash & Bank Reports';

    protected static ?string $modelLabel = 'Cash & Bank Report Entry';

    protected static ?string $pluralModelLabel = 'Cash & Bank Reports';

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transaction_date')->label('Date')->date()->sortable(),
                TextColumn::make('report_name')
                    ->label('Report')
                    ->state(fn (BankTransaction $record): string => self::reportName($record))
                    ->badge(),
                TextColumn::make('entry_from')
                    ->label('Entry From')
                    ->state(fn (BankTransaction $record): string => self::entryFrom($record))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('reference', 'like', "%{$search}%");
                    }),
                TextColumn::make('plus_amount')
                    ->label('Plus')
                    ->state(fn (BankTransaction $record): ?string => $record->type === BankTransactionType::Deposit ? $record->amount : null)
                    ->formatStateUsing(fn (mixed $state): string => filled($state) ? app_money($state) : '-')
                    ->color('success')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('amount', $direction)),
                TextColumn::make('minus_amount')
                    ->label('Minus')
                    ->state(fn (BankTransaction $record): ?string => $record->type === BankTransactionType::Withdrawal ? $record->amount : null)
                    ->formatStateUsing(fn (mixed $state): string => filled($state) ? app_money($state) : '-')
                    ->color('danger')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('amount', $direction)),
                TextColumn::make('connected_ledger')
                    ->label('Connected')
                    ->state(fn (BankTransaction $record): string => self::connectedLedger($record))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query
                            ->whereHas('ledger', fn (Builder $query): Builder => $query->where('name', 'like', "%{$search}%")->orWhere('nominal_code', 'like', "%{$search}%"))
                            ->orWhereHas('customer.ledger', fn (Builder $query): Builder => $query->where('name', 'like', "%{$search}%")->orWhere('nominal_code', 'like', "%{$search}%"))
                            ->orWhereHas('supplier.ledger', fn (Builder $query): Builder => $query->where('name', 'like', "%{$search}%")->orWhere('nominal_code', 'like', "%{$search}%"));
                    }),
                TextColumn::make('bankAccount.account_name')
                    ->label('Account')
                    ->searchable()
                    ->sortable()
                    ->description(fn (BankTransaction $record): string => $record->bankAccount?->ledger?->name ?? ''),
                TextColumn::make('reference')->searchable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('bank_account_id')->label('Account')->relationship('bankAccount', 'account_name')->searchable()->preload(),
                SelectFilter::make('type')->options(BankTransactionType::class),
                self::dateRangeFilter('transaction_date'),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'bankAccount.ledger',
                'customer.ledger',
                'supplier.ledger',
                'ledger',
                'voucher.allocations',
            ]))
            ->defaultSort('transaction_date', 'desc')
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function cashBookQuery(Builder $query): Builder
    {
        return $query
            ->whereHas('bankAccount', fn (Builder $query): Builder => $query
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

    public static function bankBookQuery(Builder $query): Builder
    {
        return $query
            ->whereHas('bankAccount', fn (Builder $query): Builder => $query
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

    public static function pettyCashBookQuery(Builder $query): Builder
    {
        return $query
            ->whereHas('bankAccount', fn (Builder $query): Builder => $query
                ->where('account_name', 'like', '%petty%')
                ->orWhere('bank_name', 'like', '%petty%')
                ->orWhereHas('ledger', fn (Builder $query): Builder => $query
                    ->where('nominal_code', '1010')
                    ->orWhere('name', 'like', '%petty%')));
    }

    public static function reportName(BankTransaction $record): string
    {
        if (self::isPettyCashAccount($record)) {
            return 'Petty Cash Book';
        }

        if (self::isCashAccount($record)) {
            return 'Cash Book';
        }

        return 'Bank Book';
    }

    private static function entryFrom(BankTransaction $record): string
    {
        if (self::isPettyCashAccount($record)) {
            return $record->type === BankTransactionType::Deposit ? 'Cash Entry' : 'Expense';
        }

        if (self::isCashAccount($record)) {
            if ($record->type === BankTransactionType::Deposit) {
                return 'Cash Receipt';
            }

            return self::voucherHasExpense($record) ? 'Expense' : 'Payment';
        }

        if ($record->voucher?->voucher_type === VoucherType::Receipt || $record->type === BankTransactionType::Deposit) {
            return 'Bank Receipt';
        }

        return 'Bank Payment';
    }

    private static function connectedLedger(BankTransaction $record): string
    {
        $ledger = $record->ledger ?: $record->customer?->ledger ?: $record->supplier?->ledger;

        if (! $ledger) {
            return 'Ledger';
        }

        return collect([$ledger->nominal_code, $ledger->name])->filter()->implode(' - ');
    }

    private static function isPettyCashAccount(BankTransaction $record): bool
    {
        return self::containsAccountText($record, 'petty') || $record->bankAccount?->ledger?->nominal_code === '1010';
    }

    private static function isCashAccount(BankTransaction $record): bool
    {
        return ! self::isPettyCashAccount($record)
            && (self::containsAccountText($record, 'cash') || $record->bankAccount?->ledger?->nominal_code === '1000');
    }

    private static function containsAccountText(BankTransaction $record, string $needle): bool
    {
        $haystack = strtolower(collect([
            $record->bankAccount?->account_name,
            $record->bankAccount?->bank_name,
            $record->bankAccount?->ledger?->name,
        ])->filter()->implode(' '));

        return str_contains($haystack, $needle);
    }

    private static function voucherHasExpense(BankTransaction $record): bool
    {
        return $record->voucher?->allocations?->contains(fn ($allocation): bool => $allocation->expense_id !== null) ?? false;
    }

    public static function getPages(): array
    {
        return ['index' => ListCashBankReports::route('/')];
    }
}
