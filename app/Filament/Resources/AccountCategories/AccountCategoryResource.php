<?php

declare(strict_types=1);

namespace App\Filament\Resources\AccountCategories;

use App\Filament\Resources\AccountCategories\Pages\ManageAccountCategories;
use App\Models\AccountCategory;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class AccountCategoryResource extends Resource
{
    protected static ?string $model = AccountCategory::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;
    protected static string|UnitEnum|null $navigationGroup = 'Accounting';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'Account Category';
    protected static ?string $modelLabel = 'Account Category';
    protected static ?string $pluralModelLabel = 'Account Categories';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Account Category')->schema([
                Select::make('account_class_id')
                    ->label('Account Class')
                    ->relationship('accountClass', 'account_class_name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('account_category_code')
                    ->label('Category Code')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('account_category_name')
                    ->label('Category Name')
                    ->required()
                    ->maxLength(255),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('account_category_id')->label('ID')->sortable(),
                TextColumn::make('accountClass.account_class_name')->label('Class')->searchable()->sortable(),
                TextColumn::make('account_category_code')->label('Category Code')->searchable()->sortable(),
                TextColumn::make('account_category_name')->label('Category Name')->searchable()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('account_category_id')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAccountCategories::route('/')];
    }
}
