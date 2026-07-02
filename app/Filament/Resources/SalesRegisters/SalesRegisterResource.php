<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesRegisters;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\SalesRegisters\Pages\ListSalesRegisters;
use App\Models\SalesInvoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SalesRegisterResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = SalesInvoice::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Sales Register';

    protected static ?string $modelLabel = 'Sales Register Entry';

    protected static ?string $pluralModelLabel = 'Sales Register';

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('status', [
                InvoiceStatus::Posted->value,
                InvoiceStatus::Paid->value,
                InvoiceStatus::Partial->value,
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_date')->label('Date')->date()->sortable(),
                TextColumn::make('invoice_no')->label('Invoice')->searchable()->sortable(),
                TextColumn::make('customer.name')->label('Customer')->searchable()->sortable(),
                TextColumn::make('subtotal')->formatStateUsing(fn (mixed $state): string => app_money($state))->sortable(),
                TextColumn::make('vat_total')->label('VAT')->formatStateUsing(fn (mixed $state): string => app_money($state))->sortable(),
                TextColumn::make('total')->label('Sales +')->formatStateUsing(fn (mixed $state): string => app_money($state))->sortable(),
                TextColumn::make('status')->badge()->sortable(),
            ])
            ->filters([
                SelectFilter::make('customer_id')->label('Customer')->relationship('customer', 'name')->searchable()->preload(),
                SelectFilter::make('status')->options([
                    InvoiceStatus::Posted->value => 'Posted',
                    InvoiceStatus::Paid->value => 'Paid',
                    InvoiceStatus::Partial->value => 'Partial',
                ]),
                self::dateRangeFilter('invoice_date'),
            ])
            ->defaultSort('invoice_date', 'desc')
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => ListSalesRegisters::route('/')];
    }
}
