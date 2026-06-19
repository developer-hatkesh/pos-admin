<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReceiptVouchers;

use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\ReceiptVouchers\Pages\ManageReceiptVouchers;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\Voucher;
use App\Services\Accounting\VoucherPostingService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ReceiptVoucherResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = Voucher::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Accounting';

    protected static ?int $navigationSort = 8;

    protected static ?string $modelLabel = 'Receipt Voucher';

    protected static ?string $pluralModelLabel = 'Receipt Vouchers';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('voucher_type', VoucherType::Receipt->value);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Receipt Voucher')->schema([
                self::companySelect(),
                Hidden::make('voucher_type')->default(VoucherType::Receipt->value),
                Hidden::make('created_by')->default(fn (): ?int => auth()->id()),
                TextInput::make('voucher_no')->disabled()->dehydrated(false)->placeholder('Auto generated'),
                DatePicker::make('voucher_date')->label('Receipt Date')->required()->default(now()),
                Select::make('bank_account_id')
                    ->label('Bank Account')
                    ->relationship('bankAccount', 'account_name')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required(),
                Placeholder::make('bank_balance')->label('Current Balance')->content(fn (Get $get): string => self::bankBalance((int) ($get('bank_account_id') ?? 0))),
                Select::make('customer_id')->relationship('customer', 'name')->searchable()->preload()->live()->required(),
                Placeholder::make('customer_balance')->label('Outstanding Balance')->content(fn (Get $get): string => self::customerBalance((int) ($get('customer_id') ?? 0))),
                self::moneyInput('amount')->required(),
                TextInput::make('reference_no')->maxLength(255),
                Select::make('status')->options(VoucherStatus::class)->default(VoucherStatus::Draft)->required(),
            ])->columns(3)->columnSpanFull(),
            Section::make('Allocations')->schema([
                Repeater::make('allocations')->relationship()->schema([
                    Select::make('sales_invoice_id')->label('Sales Invoice')->relationship('salesInvoice', 'invoice_no')->searchable()->preload()->required(),
                    self::moneyInput('amount')->required(),
                ])->columns(2)->columnSpanFull(),
            ])->columnSpanFull(),
            Section::make('Notes')->schema([
                Textarea::make('notes')->rows(3)->columnSpanFull(),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('voucher_no')->searchable()->sortable(),
                TextColumn::make('voucher_date')->date()->sortable(),
                TextColumn::make('bankAccount.account_name')->searchable(),
                TextColumn::make('customer.name')->searchable(),
                TextColumn::make('amount')->formatStateUsing(fn (mixed $state): string => app_money($state))->sortable(),
                TextColumn::make('status')->badge()->sortable(),
            ])
            ->filters([self::statusFilter(VoucherStatus::class), self::dateRangeFilter('voucher_date')])
            ->defaultSort('voucher_date', 'desc')
            ->recordActions([
                Action::make('post')
                    ->icon(Heroicon::CheckCircle)
                    ->requiresConfirmation()
                    ->visible(fn (Voucher $record): bool => $record->status === VoucherStatus::Draft)
                    ->action(function (Voucher $record): void {
                        app(VoucherPostingService::class)->post($record);
                        Notification::make()->title('Receipt voucher posted')->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageReceiptVouchers::route('/')];
    }

    private static function bankBalance(int $bankAccountId): string
    {
        $bankAccount = BankAccount::query()->find($bankAccountId);

        return $bankAccount ? app_money($bankAccount->currentBalance()) : 'Select a bank account';
    }

    private static function customerBalance(int $customerId): string
    {
        $customer = Customer::query()->find($customerId);

        if (! $customer) {
            return 'Select a customer';
        }

        $sales = (float) SalesInvoice::withoutGlobalScopes()->where('customer_id', $customerId)->sum('total');
        $returns = (float) SalesReturn::withoutGlobalScopes()->where('customer_id', $customerId)->sum('total');
        $receipts = (float) Voucher::withoutGlobalScopes()
            ->where('voucher_type', VoucherType::Receipt->value)
            ->where('customer_id', $customerId)
            ->where('status', VoucherStatus::Posted->value)
            ->sum('amount');

        return app_money(round((float) $customer->opening_balance + $sales - $returns - $receipts, 2));
    }
}
