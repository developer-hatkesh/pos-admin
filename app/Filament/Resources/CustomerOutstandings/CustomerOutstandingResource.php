<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomerOutstandings;

use App\Enums\Status;
use App\Filament\Resources\CustomerOutstandings\Pages\ListCustomerOutstandings;
use App\Models\Customer;
use App\Support\Sales\SalesReportSql;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class CustomerOutstandingResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Customer Outstanding';

    protected static ?string $modelLabel = 'Customer Outstanding';

    protected static ?string $pluralModelLabel = 'Customer Outstanding';

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->select('customers.*')
            ->selectRaw(SalesReportSql::salesSql().' as sales_total')
            ->selectRaw(SalesReportSql::receiptSql().' as receipt_total')
            ->selectRaw(SalesReportSql::creditNoteSql().' as credit_note_total')
            ->selectRaw(SalesReportSql::outstandingSql().' as outstanding_total');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Customer $record): string => collect([
                        $record->customer_code,
                        $record->phone,
                    ])->filter()->implode(' | ')),
                TextColumn::make('sales_total')
                    ->label('Sales')
                    ->formatStateUsing(fn (mixed $state): string => app_money($state))
                    ->sortable(),
                TextColumn::make('receipt_total')
                    ->label('Receipts')
                    ->formatStateUsing(fn (mixed $state): string => app_money($state))
                    ->sortable(),
                TextColumn::make('credit_note_total')
                    ->label('Credit Notes')
                    ->formatStateUsing(fn (mixed $state): string => app_money($state))
                    ->sortable(),
                TextColumn::make('outstanding_total')
                    ->label('Outstanding')
                    ->formatStateUsing(fn (mixed $state): string => app_money($state))
                    ->sortable()
                    ->color(fn (mixed $state): string => ((float) $state > 0) ? 'warning' : 'success'),
                TextColumn::make('status')->badge()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(Status::class),
                Filter::make('with_balance')
                    ->label('Only outstanding')
                    ->query(fn (Builder $query): Builder => $query->whereRaw(SalesReportSql::outstandingSql().' <> 0')),
            ])
            ->defaultSort('name')
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => ListCustomerOutstandings::route('/')];
    }
}
