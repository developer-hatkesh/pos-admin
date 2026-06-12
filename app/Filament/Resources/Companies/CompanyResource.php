<?php

declare(strict_types=1);

namespace App\Filament\Resources\Companies;

use App\Filament\Resources\Companies\Pages\ManageCompanies;
use App\Models\Company;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;
    protected static string|UnitEnum|null $navigationGroup = 'System';
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'Company';
    protected static ?string $pluralModelLabel = 'Companies';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Company')->schema([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('email')->email()->maxLength(255),
                TextInput::make('phone')->maxLength(255),
                TextInput::make('vat_number')->maxLength(255),
                TextInput::make('currency')->required()->default('GBP')->maxLength(3),
            ])->columns(2),
            Section::make('Address')->schema([
                TextInput::make('address')->columnSpanFull(),
                TextInput::make('city')->maxLength(255),
                TextInput::make('postcode')->maxLength(255),
                TextInput::make('country')->required()->default('UK')->maxLength(255),
                DatePicker::make('financial_year_start'),
                DatePicker::make('financial_year_end'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('vat_number')->searchable(),
                TextColumn::make('currency')->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCompanies::route('/')];
    }
}
