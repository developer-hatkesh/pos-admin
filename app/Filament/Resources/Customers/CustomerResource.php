<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers;

use App\Enums\BalanceType;
use App\Enums\Status;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Models\Customer;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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

class CustomerResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = Customer::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;
    protected static string|UnitEnum|null $navigationGroup = 'Contacts';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Customers';
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
                TextInput::make('customer_code')
                    ->label('Customer Code')
                    ->default(fn (): string => self::nextCustomerCodePreview())
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('company_name')->label('Company Name')->maxLength(255),
                TextInput::make('contact_person')->label('Contact Person')->maxLength(255),
                TextInput::make('email')->email()->maxLength(255),
                TextInput::make('mobile_no')->label('Mobile Number')->maxLength(255),
                TextInput::make('telephone_no')->label('Telephone')->maxLength(255),
                TextInput::make('website')->url()->maxLength(255),
                TextInput::make('tax_number')->label('Tax/VAT Number')->maxLength(255),
                Select::make('currency_id')
                    ->label('Currency')
                    ->options([
                        'GBP' => 'GBP',
                        'EUR' => 'euro',
                        'USD' => 'usd',
                    ])
                    ->default('GBP')
                    ->required(),
                TextInput::make('tax_code_id')->label('Tax Code')->maxLength(255),
                TextInput::make('discount_percent')
                    ->label('Discount %')
                    ->numeric()
                    ->default(0)
                    ->step('0.01')
                    ->minValue(0)
                    ->maxValue(100),
                Select::make('price_type')
                    ->label('Price Type')
                    ->options([
                        'retail' => 'Retail Price',
                        'wholesale' => 'Wholesale Price',
                    ])
                    ->default('retail')
                    ->required(),
                self::moneyInput('credit_limit')->label('Credit Limit'),
                TextInput::make('payment_terms_days')
                    ->label('Payment Terms (Days)')
                    ->integer()
                    ->minValue(0),
                self::moneyInput('opening_balance')->label('Opening Balance'),
                Select::make('balance_type')->options(BalanceType::class),
                Select::make('status')->options(Status::class)->default(Status::Active)->required(),
            ])->columns(2)->columnSpanFull(),
            Section::make('Address')->schema([
                Textarea::make('billing_address')->columnSpanFull(),
                Textarea::make('delivery_address')->columnSpanFull(),
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
            TextColumn::make('customer_code')->label('Customer Code')->searchable()->sortable(),
            TextColumn::make('company_name')->label('Company Name')->searchable()->sortable(),
            TextColumn::make('contact_person')->searchable(),
            TextColumn::make('mobile_no')->label('Mobile')->searchable(),
            TextColumn::make('email')->searchable(),
            TextColumn::make('currency_id')->label('Currency')->sortable(),
            TextColumn::make('price_type')
                ->label('Price Type')
                ->formatStateUsing(fn (?string $state): string => $state === 'wholesale' ? 'Wholesale' : 'Retail')
                ->badge()
                ->sortable(),
            TextColumn::make('status')->badge()->sortable(),
            TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([self::statusFilter(Status::class)])
            ->defaultSort('created_at', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }

    private static function nextCustomerCodePreview(): string
    {
        $lastId = Customer::query()
            ->withoutGlobalScope('company')
            ->max('id') ?? 0;

        return sprintf('CUST%03d', $lastId + 1);
    }
}
