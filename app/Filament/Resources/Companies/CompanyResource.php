<?php

declare(strict_types=1);

namespace App\Filament\Resources\Companies;

use App\Filament\Resources\Companies\Pages\CreateCompany;
use App\Filament\Resources\Companies\Pages\EditCompany;
use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Models\Company;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
                TextInput::make('name')->label('Business Name')->required()->maxLength(255),
                TextInput::make('contact_person_name')->label('Contact Person Name')->required()->maxLength(255),
                TextInput::make('phone')->label('Phone No')->required()->maxLength(255),
                TextInput::make('email')->label('Email ID')->email()->required()->maxLength(255),
                TextInput::make('website')->url()->maxLength(255),
                TextInput::make('currency')->required()->default('GBP')->maxLength(3),
                TextInput::make('legal_business_name')->required()->maxLength(255),
                TextInput::make('vat_number')->label('Tax No / VAT Number')->maxLength(255),
                TextInput::make('company_house_number')->label('Company House No')->maxLength(255),
                TextInput::make('business_phone_number')->required()->maxLength(255),
                Select::make('number_of_employees')
                    ->options([
                        'SOLO' => 'SOLO',
                        '1' => '1',
                        '2' => '2',
                        '3' => '3',
                        '4' => '4',
                        '5' => '5',
                        '5-10' => '5-10',
                        '11-15' => '11-15',
                        '16-20' => '16-20',
                        '20+' => '20+',
                    ])
                    ->required(),
                Textarea::make('additional_information')->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
            Section::make('Address')->schema([
                Textarea::make('address')->label('Registered Business Address')->required()->columnSpanFull(),
                TextInput::make('city')->required()->maxLength(255),
                TextInput::make('postcode')->required()->maxLength(255),
                TextInput::make('country')->required()->default('UK')->maxLength(255),
                DatePicker::make('financial_year_start')->required(),
                DatePicker::make('financial_year_end')->required(),
                Textarea::make('notes')->label('Note')->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('contact_person_name')->searchable(),
                TextColumn::make('phone')->searchable(),
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
        return [
            'index' => ListCompanies::route('/'),
            'create' => CreateCompany::route('/create'),
            'edit' => EditCompany::route('/{record}/edit'),
        ];
    }
}
