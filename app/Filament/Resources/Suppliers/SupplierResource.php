<?php

declare(strict_types=1);

namespace App\Filament\Resources\Suppliers;

use App\Enums\PartyType;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Suppliers\Pages\ManageSuppliers;
use App\Models\Party;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class SupplierResource extends CustomerResource
{
    protected static ?string $model = Party::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;
    protected static string|UnitEnum|null $navigationGroup = 'Purchasing';
    protected static ?int $navigationSort = 2;
    protected static ?string $modelLabel = 'Supplier';
    protected static ?string $pluralModelLabel = 'Suppliers';

    public static function getEloquentQuery(): Builder
    {
        return static::getModel()::query()->where('type', PartyType::Supplier->value);
    }

    public static function form(Schema $schema): Schema
    {
        return self::partyForm($schema, PartyType::Supplier);
    }

    public static function table(Table $table): Table
    {
        return parent::table($table);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSuppliers::route('/')];
    }
}
