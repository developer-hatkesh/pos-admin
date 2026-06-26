<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupplierOutstandings;

use App\Enums\Status;
use App\Filament\Resources\SupplierOutstandings\Pages\ListSupplierOutstandings;
use App\Models\Supplier;
use App\Support\Purchases\PurchaseReportSql;
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

class SupplierOutstandingResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 11;

    protected static ?string $navigationLabel = 'Supplier Outstanding';

    protected static ?string $modelLabel = 'Supplier Outstanding';

    protected static ?string $pluralModelLabel = 'Supplier Outstanding';

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->select('suppliers.*')
            ->selectRaw(PurchaseReportSql::purchaseSql().' as purchase_total')
            ->selectRaw(PurchaseReportSql::paymentSql().' as payment_total')
            ->selectRaw(PurchaseReportSql::debitNoteSql().' as debit_note_total')
            ->selectRaw(PurchaseReportSql::outstandingSql().' as outstanding_total');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Supplier $record): string => collect([
                        $record->supplier_code,
                        $record->phone,
                    ])->filter()->implode(' | ')),
                TextColumn::make('purchase_total')
                    ->label('Purchases')
                    ->formatStateUsing(fn (mixed $state): string => app_money($state))
                    ->sortable(),
                TextColumn::make('payment_total')
                    ->label('Payments')
                    ->formatStateUsing(fn (mixed $state): string => app_money($state))
                    ->sortable(),
                TextColumn::make('debit_note_total')
                    ->label('Debit Notes')
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
                    ->query(fn (Builder $query): Builder => $query->whereRaw(PurchaseReportSql::outstandingSql().' <> 0')),
            ])
            ->defaultSort('name')
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => ListSupplierOutstandings::route('/')];
    }
}
