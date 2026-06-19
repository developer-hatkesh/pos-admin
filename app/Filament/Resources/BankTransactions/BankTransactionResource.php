<?php

declare(strict_types=1);

namespace App\Filament\Resources\BankTransactions;

use App\Enums\BankTransactionType;
use App\Filament\Resources\BankTransactions\Pages\ManageBankTransactions;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Models\BankTransaction;
use App\Services\Accounting\BankPostingService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class BankTransactionResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = BankTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Other';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Bank Transaction';

    protected static ?string $modelLabel = 'Bank Transaction';

    protected static ?string $pluralModelLabel = 'Bank Transactions';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Transaction')->schema([
                self::companySelect(),
                Select::make('bank_account_id')->relationship('bankAccount', 'account_name')->searchable()->preload()->required(),
                DatePicker::make('transaction_date')->required()->default(now()),
                Select::make('type')->options(BankTransactionType::class)->required(),
                self::moneyInput('amount')->required(),
                TextInput::make('reference')->maxLength(255),
                Select::make('customer_id')->relationship('customer', 'name')->searchable()->preload(),
                Select::make('supplier_id')->relationship('supplier', 'name')->searchable()->preload(),
                Select::make('ledger_id')->relationship('ledger', 'name')->searchable()->preload(),
                Toggle::make('reconciled'),
            ])->columns(3)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('bankAccount.account_name')->searchable()->sortable(),
            TextColumn::make('transaction_date')->date()->sortable(),
            TextColumn::make('type')->badge()->sortable(),
            TextColumn::make('amount')->formatStateUsing(fn (mixed $state): string => app_money($state))->sortable(),
            TextColumn::make('customer.name')->searchable(),
            TextColumn::make('supplier.name')->searchable(),
            IconColumn::make('reconciled')->boolean(),
        ])->filters([SelectFilter::make('type')->options(BankTransactionType::class), self::dateRangeFilter('transaction_date')])
            ->defaultSort('transaction_date', 'desc')
            ->recordActions([
                Action::make('post')->icon(Heroicon::CheckCircle)->requiresConfirmation()->visible(fn (BankTransaction $record): bool => $record->journal_id === null)->action(function (BankTransaction $record): void {
                    app(BankPostingService::class)->post($record);
                    Notification::make()->title('Bank transaction posted')->success()->send();
                }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageBankTransactions::route('/')];
    }
}
