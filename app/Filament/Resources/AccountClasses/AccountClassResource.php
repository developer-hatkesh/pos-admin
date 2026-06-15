<?php

declare(strict_types=1);

namespace App\Filament\Resources\AccountClasses;

use App\Filament\Resources\AccountClasses\Pages\ManageAccountClasses;
use App\Models\AccountClass;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class AccountClassResource extends Resource
{
    protected static ?string $model = AccountClass::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Accounting';
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'Account Class';
    protected static ?string $pluralModelLabel = 'Account Classes';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Account Class')->schema([
                TextInput::make('account_class_code')
                    ->label('Class Code')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('account_class_name')
                    ->label('Class Name')
                    ->required()
                    ->maxLength(255),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('account_class_id')->label('ID')->sortable(),
                TextColumn::make('account_class_code')->label('Class Code')->searchable()->sortable(),
                TextColumn::make('account_class_name')->label('Class Name')->searchable()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('account_class_id')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAccountClasses::route('/')];
    }
}
