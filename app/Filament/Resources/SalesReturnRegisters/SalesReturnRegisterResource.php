<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesReturnRegisters;

use App\Enums\SalesReturnStatus;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\SalesReturnRegisters\Pages\ListSalesReturnRegisters;
use App\Models\SalesReturn;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SalesReturnRegisterResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = SalesReturn::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Sales Return Register';

    protected static ?string $modelLabel = 'Sales Return Register Entry';

    protected static ?string $pluralModelLabel = 'Sales Return Register';

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('status', SalesReturnStatus::Posted->value);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('return_date')->label('Date')->date()->sortable(),
                TextColumn::make('return_no')->label('Credit Note')->searchable()->sortable(),
                TextColumn::make('salesInvoice.invoice_no')->label('Invoice')->searchable(),
                TextColumn::make('customer.name')->label('Customer')->searchable()->sortable(),
                TextColumn::make('subtotal')->formatStateUsing(fn (mixed $state): string => app_money($state))->sortable(),
                TextColumn::make('vat_total')->label('VAT')->formatStateUsing(fn (mixed $state): string => app_money($state))->sortable(),
                TextColumn::make('total')->label('Credit -')->formatStateUsing(fn (mixed $state): string => app_money($state))->sortable()->color('danger'),
                TextColumn::make('status')->badge()->sortable(),
            ])
            ->filters([
                SelectFilter::make('customer_id')->label('Customer')->relationship('customer', 'name')->searchable()->preload(),
                self::dateRangeFilter('return_date'),
            ])
            ->defaultSort('return_date', 'desc')
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => ListSalesReturnRegisters::route('/')];
    }
}
