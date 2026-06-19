<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExpenseCategories;

use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\ExpenseCategories\Pages\ManageExpenseCategories;
use App\Models\ExpenseCategory;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class ExpenseCategoryResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = ExpenseCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static string|UnitEnum|null $navigationGroup = 'Expenses';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Expense Category';

    protected static ?string $pluralModelLabel = 'Expense Categories';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Expense Category')->schema([
                self::companySelect(),
                TextInput::make('category_code')->required()->maxLength(50),
                TextInput::make('category_name')->required()->maxLength(255),
                Select::make('ledger_id')
                    ->label('Ledger Account')
                    ->relationship('ledger', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Toggle::make('is_active')->default(true),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category_code')->searchable()->sortable(),
                TextColumn::make('category_name')->searchable()->sortable(),
                TextColumn::make('ledger.name')->searchable()->sortable(),
                IconColumn::make('is_active')->boolean()->sortable(),
            ])
            ->filters([TernaryFilter::make('is_active')->label('Active')])
            ->defaultSort('category_name')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageExpenseCategories::route('/')];
    }
}
