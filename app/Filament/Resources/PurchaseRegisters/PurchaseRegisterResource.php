<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseRegisters;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\PurchaseRegisters\Pages\ListPurchaseRegisters;
use App\Models\PurchaseInvoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PurchaseRegisterResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = PurchaseInvoice::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'Purchase Register';

    protected static ?string $modelLabel = 'Purchase Register Entry';

    protected static ?string $pluralModelLabel = 'Purchase Register';

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
                TextColumn::make('invoice_no')->label('Purchase Invoice')->searchable()->sortable(),
                TextColumn::make('supplier.name')->label('Supplier')->searchable()->sortable(),
                TextColumn::make('subtotal')->formatStateUsing(fn (mixed $state): string => app_money($state))->sortable(),
                TextColumn::make('vat_total')->label('VAT')->formatStateUsing(fn (mixed $state): string => app_money($state))->sortable(),
                TextColumn::make('total')->label('Purchase +')->formatStateUsing(fn (mixed $state): string => app_money($state))->sortable(),
                TextColumn::make('status')->badge()->sortable(),
            ])
            ->filters([
                SelectFilter::make('supplier_id')->label('Supplier')->relationship('supplier', 'name')->searchable()->preload(),
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
        return ['index' => ListPurchaseRegisters::route('/')];
    }
}
