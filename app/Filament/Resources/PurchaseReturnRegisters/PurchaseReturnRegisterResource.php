<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseReturnRegisters;

use App\Enums\PurchaseReturnStatus;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\PurchaseReturnRegisters\Pages\ListPurchaseReturnRegisters;
use App\Models\PurchaseReturn;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PurchaseReturnRegisterResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = PurchaseReturn::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'Purchase Return Register';

    protected static ?string $modelLabel = 'Purchase Return Register Entry';

    protected static ?string $pluralModelLabel = 'Purchase Return Register';

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('status', PurchaseReturnStatus::Posted->value);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('return_date')->label('Date')->date()->sortable(),
                TextColumn::make('return_no')->label('Debit Note')->searchable()->sortable(),
                TextColumn::make('purchaseInvoice.invoice_no')->label('Purchase Invoice')->searchable(),
                TextColumn::make('supplier.name')->label('Supplier')->searchable()->sortable(),
                TextColumn::make('subtotal')->formatStateUsing(fn (mixed $state): string => app_money($state))->sortable(),
                TextColumn::make('vat_total')->label('VAT')->formatStateUsing(fn (mixed $state): string => app_money($state))->sortable(),
                TextColumn::make('total')->label('Debit Note -')->formatStateUsing(fn (mixed $state): string => app_money($state))->sortable()->color('danger'),
                TextColumn::make('status')->badge()->sortable(),
            ])
            ->filters([
                SelectFilter::make('supplier_id')->label('Supplier')->relationship('supplier', 'name')->searchable()->preload(),
                self::dateRangeFilter('return_date'),
            ])
            ->defaultSort('return_date', 'desc')
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPurchaseReturnRegisters::route('/')];
    }
}
