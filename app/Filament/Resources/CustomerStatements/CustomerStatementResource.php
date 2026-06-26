<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomerStatements;

use App\Enums\Status;
use App\Filament\Resources\CustomerStatements\Pages\ListCustomerStatements;
use App\Models\Customer;
use App\Support\Sales\SalesReportSql;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class CustomerStatementResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Customer Statement';

    protected static ?string $modelLabel = 'Customer Statement';

    protected static ?string $pluralModelLabel = 'Customer Statements';

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->select('customers.*')
            ->selectRaw(SalesReportSql::openingBalanceSql().' as opening_balance_effect')
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
                TextColumn::make('opening_balance_effect')
                    ->label('Opening')
                    ->formatStateUsing(fn (mixed $state): string => app_money($state))
                    ->sortable(),
                TextColumn::make('sales_total')
                    ->label('Sales +')
                    ->formatStateUsing(fn (mixed $state): string => app_money($state))
                    ->sortable()
                    ->color('success'),
                TextColumn::make('receipt_total')
                    ->label('Receipts -')
                    ->formatStateUsing(fn (mixed $state): string => app_money($state))
                    ->sortable(),
                TextColumn::make('credit_note_total')
                    ->label('Credit Notes -')
                    ->formatStateUsing(fn (mixed $state): string => app_money($state))
                    ->sortable()
                    ->color('danger'),
                TextColumn::make('outstanding_total')
                    ->label('Outstanding')
                    ->formatStateUsing(fn (mixed $state): string => app_money($state))
                    ->sortable()
                    ->color(fn (mixed $state): string => ((float) $state > 0) ? 'warning' : 'success'),
                TextColumn::make('status')->badge()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(Status::class),
            ])
            ->defaultSort('name')
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => ListCustomerStatements::route('/')];
    }
}
