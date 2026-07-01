<?php

declare(strict_types=1);

namespace App\Filament\Resources\Suppliers;

use App\Enums\BalanceType;
use App\Enums\Status;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Resources\Suppliers\Pages\ListSuppliers;
use App\Models\Supplier;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class SupplierResource extends CustomerResource
{
    protected static ?string $model = Supplier::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;
    protected static string|UnitEnum|null $navigationGroup = 'Contacts';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Suppliers';
    protected static ?string $modelLabel = 'Supplier';
    protected static ?string $pluralModelLabel = 'Suppliers';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Supplier')->schema([
                self::companySelect(),
                TextInput::make('supplier_code')
                    ->label('Supplier Code')
                    ->default(fn (): string => self::nextSupplierCodePreview())
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('company_name')->label('Company Name')->maxLength(255),
                TextInput::make('contact_person')->label('Contact Person')->maxLength(255),
                TextInput::make('email')->label('Email Id')->email()->maxLength(255),
                TextInput::make('mobile_no')->label('Mobile Number')->maxLength(255),
                TextInput::make('telephone_no')->label('Telephone')->maxLength(255),
                TextInput::make('website')->url()->maxLength(255),
                TextInput::make('tax_number')->label('VAT / Tax Number')->maxLength(255),
                Select::make('currency_id')
                    ->label('Currency')
                    ->options([
                        'GBP' => 'GBP',
                        'EUR' => 'euro',
                        'USD' => 'usd',
                    ])
                    ->default('GBP')
                    ->required(),
                TextInput::make('payment_terms')
                    ->label('Payment Terms (Days)')
                    ->integer()
                    ->minValue(0),
                self::moneyInput('opening_balance')->label('Opening Balance'),
                Select::make('balance_type')
                    ->label('Balance Type')
                    ->options(BalanceType::class)
                    ->default(BalanceType::Credit)
                    ->required(),
                TextInput::make('bank_name')->label('Bank Details')->maxLength(255),
                Select::make('status')->options(Status::class)->default(Status::Active)->required(),
            ])->columns(2)->columnSpanFull(),
            Section::make('Address')->schema([
                Textarea::make('address')->columnSpanFull(),
                TextInput::make('city')->maxLength(255),
                TextInput::make('postcode')->maxLength(255),
                TextInput::make('country')->default('UK')->maxLength(255),
                Textarea::make('notes')->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('supplier_code')->label('Supplier Code')->searchable()->sortable(),
            TextColumn::make('company_name')->label('Company Name')->searchable()->sortable(),
            TextColumn::make('contact_person')->searchable(),
            TextColumn::make('email')->searchable(),
            TextColumn::make('mobile_no')->label('Mobile')->searchable(),
            TextColumn::make('currency_id')->label('Currency')->sortable(),
            TextColumn::make('status')->badge()->sortable(),
            TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([self::statusFilter(Status::class)])
            ->defaultSort('created_at', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    private static function nextSupplierCodePreview(): string
    {
        $lastId = Supplier::query()
            ->withoutGlobalScope('company')
            ->max('id') ?? 0;

        return sprintf('SUP%03d', $lastId + 1);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuppliers::route('/'),
            'create' => CreateSupplier::route('/create'),
            'edit' => EditSupplier::route('/{record}/edit'),
        ];
    }
}
