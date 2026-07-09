<?php

declare(strict_types=1);

namespace App\Filament\Resources\BankAccounts;

use App\Enums\LedgerType;
use App\Enums\Status;
use App\Filament\Resources\BankAccounts\Pages\ManageBankAccounts;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Ledger;
use App\Support\CurrentCompany;
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

    protected static string|UnitEnum|null $navigationGroup = 'Accounts';

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
                    ->options(fn (): array => self::chartAccountOptions())
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
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data): array => self::prepareDataForForm($data))
                    ->mutateDataUsing(fn (array $data): array => self::prepareDataForSave($data)),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageBankAccounts::route('/')];
    }

    public static function prepareDataForForm(array $data): array
    {
        $data['ledger_id'] = self::chartAccountIdForLedgerId($data['ledger_id'] ?? null);

        return $data;
    }

    public static function prepareDataForSave(array $data): array
    {
        $data['ledger_id'] = self::ledgerIdForChartAccountId(
            $data['ledger_id'] ?? null,
            $data['company_id'] ?? app(CurrentCompany::class)->id(),
        );

        return $data;
    }

    private static function chartAccountOptions(): array
    {
        return ChartOfAccount::query()
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get(['account_id', 'account_code', 'account_name'])
            ->mapWithKeys(fn (ChartOfAccount $account): array => [
                $account->account_id => "{$account->account_code} - {$account->account_name}",
            ])
            ->all();
    }

    private static function chartAccountIdForLedgerId(mixed $ledgerId): ?int
    {
        if (blank($ledgerId)) {
            return null;
        }

        $ledger = Ledger::query()
            ->withoutGlobalScope('company')
            ->find($ledgerId);

        if (! $ledger) {
            return null;
        }

        return ChartOfAccount::query()
            ->where('account_code', $ledger->nominal_code)
            ->value('account_id');
    }

    private static function ledgerIdForChartAccountId(mixed $chartAccountId, mixed $companyId): ?int
    {
        if (blank($chartAccountId)) {
            return null;
        }

        $companyId = filled($companyId) ? (int) $companyId : app(CurrentCompany::class)->id();

        if ($companyId === null) {
            return null;
        }

        $chartAccount = ChartOfAccount::query()
            ->with('accountCategory.accountClass')
            ->find($chartAccountId);

        if (! $chartAccount) {
            return null;
        }

        $ledger = Ledger::query()
            ->withoutGlobalScope('company')
            ->firstOrNew([
                'company_id' => $companyId,
                'nominal_code' => $chartAccount->account_code,
            ]);

        if (! $ledger->exists) {
            $ledger->fill([
                'name' => $chartAccount->account_name,
                'type' => self::ledgerTypeForChartAccount($chartAccount),
                'is_control_account' => true,
                'opening_balance' => 0,
                'balance_type' => self::balanceTypeForChartAccount($chartAccount),
                'status' => Status::Active,
            ]);
        }

        $ledger->save();

        return $ledger->id;
    }

    private static function ledgerTypeForChartAccount(ChartOfAccount $chartAccount): string
    {
        return match ($chartAccount->accountCategory?->accountClass?->account_class_code) {
            'LIABILITY' => LedgerType::Liability->value,
            'EQUITY' => LedgerType::Equity->value,
            'INCOME' => LedgerType::Income->value,
            'EXPENSE' => LedgerType::Expense->value,
            default => LedgerType::Asset->value,
        };
    }

    private static function balanceTypeForChartAccount(ChartOfAccount $chartAccount): string
    {
        return strtoupper((string) $chartAccount->normal_balance_type) === 'CREDIT' ? 'Cr' : 'Dr';
    }
}
