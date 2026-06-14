<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers;

use App\Enums\BalanceType;
use App\Enums\PaymentTerms;
use App\Enums\Status;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\Customers\Pages\ManageCustomers;
use App\Models\Customer;
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

class CustomerResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = Customer::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;
    protected static string|UnitEnum|null $navigationGroup = 'POS / Sales';
    protected static ?int $navigationSort = 3;
    protected static ?string $modelLabel = 'Customer';
    protected static ?string $pluralModelLabel = 'Customers';

    public static function form(Schema $schema): Schema
    {
        return self::contactForm($schema, 'Customer');
    }

    public static function contactForm(Schema $schema, string $sectionTitle): Schema
    {
        return $schema->components([
            Section::make($sectionTitle)->schema([
                self::companySelect(),
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('phone')->maxLength(255),
                TextInput::make('email')->email()->maxLength(255),
                TextInput::make('vat_number')->maxLength(255),
                Select::make('payment_terms')->options(PaymentTerms::class),
                self::moneyInput('credit_limit'),
                self::moneyInput('opening_balance'),
                Select::make('balance_type')->options(BalanceType::class),
                Select::make('ledger_id')->relationship('ledger', 'name')->searchable()->preload(),
                Select::make('status')->options(Status::class)->default(Status::Active)->required(),
            ])->columns(2)->columnSpanFull(),
            Section::make('Address')->schema([
                TextInput::make('address_line1')->maxLength(255),
                TextInput::make('address_line2')->maxLength(255),
                TextInput::make('city')->maxLength(255),
                TextInput::make('postcode')->maxLength(255),
                TextInput::make('country')->default('UK')->maxLength(255),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('company.name')->searchable()->sortable(),
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('phone')->searchable(),
            TextColumn::make('email')->searchable(),
            TextColumn::make('ledger.name')->searchable(),
            TextColumn::make('status')->badge()->sortable(),
            TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([self::companyFilter(), self::statusFilter(Status::class)])
            ->defaultSort('created_at', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCustomers::route('/')];
    }
}
