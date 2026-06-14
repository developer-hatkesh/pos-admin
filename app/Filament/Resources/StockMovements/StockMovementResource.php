<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockMovements;

use App\Enums\StockMovementType;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\StockMovements\Pages\ManageStockMovements;
use App\Models\StockMovement;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class StockMovementResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = StockMovement::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;
    protected static string|UnitEnum|null $navigationGroup = 'Inventory';
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'Stock Movement';
    protected static ?string $pluralModelLabel = 'Stock Movements';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Movement')->schema([
                self::companySelect(),
                Select::make('product_item_id')->relationship('productItem', 'name')->searchable()->preload()->required(),
                Select::make('type')->options(StockMovementType::class)->required(),
                TextInput::make('quantity')->numeric()->required()->step('0.001'),
                self::moneyInput('rate'),
                DatePicker::make('movement_date')->required()->default(now()),
                TextInput::make('reference_type')->maxLength(255),
                TextInput::make('reference_id')->numeric(),
            ])->columns(3)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('company.name')->searchable()->sortable(),
            TextColumn::make('productItem.name')->searchable()->sortable(),
            TextColumn::make('type')->badge()->sortable(),
            TextColumn::make('quantity')->sortable(),
            TextColumn::make('rate')->money('GBP')->sortable(),
            TextColumn::make('movement_date')->date()->sortable(),
        ])->filters([self::companyFilter(), SelectFilter::make('type')->options(StockMovementType::class), self::dateRangeFilter('movement_date')])
            ->defaultSort('movement_date', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array { return ['index' => ManageStockMovements::route('/')]; }
}
