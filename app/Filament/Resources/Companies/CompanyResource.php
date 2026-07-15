<?php

declare(strict_types=1);

namespace App\Filament\Resources\Companies;

use App\Filament\Resources\Companies\Pages\CreateCompany;
use App\Filament\Resources\Companies\Pages\EditCompany;
use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Models\Company;
use App\Models\User;
use App\Support\CurrentCompany;
use BackedEnum;
use Filament\Actions\Action;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use UnitEnum;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;
    protected static string|UnitEnum|null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Company Settings';
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
            Section::make('Company Admin User')->schema([
                TextInput::make('company_admin_name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('company_admin_email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->unique(table: 'users', column: 'email')
                    ->maxLength(255),
                TextInput::make('company_admin_password')
                    ->label('Password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->minLength(8)
                    ->maxLength(255),
            ])
                ->columns(2)
                ->columnSpanFull()
                ->visible(fn (string $operation): bool => $operation === 'create' && auth()->user()?->hasSuperAdminRole() === true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('contact_person_name')
                    ->searchable()
                    ->action(
                        Action::make('manageCompanyAdmin')
                            ->modalHeading(fn (Company $record): string => "Manage {$record->contact_person_name}")
                            ->modalDescription('Update the login details for this company administrator.')
                            ->modalSubmitActionLabel('Update user')
                            ->fillForm(function (Company $record): array {
                                $companyAdmin = static::companyAdminFor($record);

                                return [
                                    'email' => $companyAdmin?->email,
                                    'password' => null,
                                    'password_confirmation' => null,
                                ];
                            })
                            ->schema([
                                TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->required()
                                    ->maxLength(255)
                                    ->rules(fn (Company $record): array => [
                                        Rule::unique('users', 'email')->ignore(static::companyAdminFor($record)?->getKey()),
                                    ]),
                                TextInput::make('password')
                                    ->label('New password')
                                    ->password()
                                    ->revealable()
                                    ->minLength(8)
                                    ->maxLength(255)
                                    ->same('password_confirmation')
                                    ->helperText('Leave blank to keep the current password.'),
                                TextInput::make('password_confirmation')
                                    ->label('Confirm new password')
                                    ->password()
                                    ->revealable()
                                    ->requiredWith('password'),
                            ])
                            ->visible(fn (Company $record): bool => auth()->user()?->isPlatformSuperAdmin() === true
                                && static::companyAdminFor($record) !== null)
                            ->action(function (Company $record, array $data): void {
                                abort_unless(auth()->user()?->isPlatformSuperAdmin() === true, 403);

                                $companyAdmin = static::companyAdminFor($record);
                                abort_if($companyAdmin === null, 404);

                                $updates = ['email' => $data['email']];

                                if (filled($data['password'] ?? null)) {
                                    $updates['password'] = $data['password'];
                                }

                                $companyAdmin->update($updates);
                            })
                            ->successNotificationTitle('Company administrator updated'),
                    ),
                TextColumn::make('phone')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('vat_number')->searchable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isPlatformSuperAdmin()) {
            return $query;
        }

        return $query->whereKey(app(CurrentCompany::class)->companiesFor($user)->pluck('id'));
    }

    private static function companyAdminFor(Company $company): ?User
    {
        return $company->primaryUsers()->oldest('id')->first();
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
