<?php

declare(strict_types=1);

namespace App\Filament\Resources\BankAccounts;

use App\Enums\Status;
use App\Filament\Resources\BankAccounts\Pages\ManageBankAccounts;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Models\BankAccount;
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

class BankAccountResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = BankAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Other';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Bank Accounts';

    protected static ?string $modelLabel = 'Bank Account';

    protected static ?string $pluralModelLabel = 'Bank Accounts';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Bank Account')->schema([
                self::companySelect(),
                Select::make('ledger_id')
                    ->label('Ledger Account')
                    ->relationship('ledger', 'name')
                    ->searchable()
                    ->preload(),
                TextInput::make('bank_name')->required()->maxLength(255),
                TextInput::make('account_name')->required()->maxLength(255),
                TextInput::make('account_number')->maxLength(255),
                TextInput::make('sort_code')->maxLength(255),
                self::moneyInput('opening_balance'),
                Select::make('status')->options(Status::class)->default(Status::Active)->required(),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('bank_name')->searchable()->sortable(),
            TextColumn::make('account_name')->searchable()->sortable(),
            TextColumn::make('opening_balance')->formatStateUsing(fn (mixed $state): string => app_money($state))->sortable(),
            TextColumn::make('status')->badge()->sortable(),
        ])->filters([self::statusFilter(Status::class)])
            ->defaultSort('created_at', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageBankAccounts::route('/')];
    }
}
