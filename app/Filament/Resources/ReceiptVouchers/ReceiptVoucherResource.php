<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReceiptVouchers;

use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\ReceiptVouchers\Pages\CreateReceiptVoucher;
use App\Filament\Resources\ReceiptVouchers\Pages\EditReceiptVoucher;
use App\Filament\Resources\ReceiptVouchers\Pages\ListReceiptVouchers;
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
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
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

    protected static string|UnitEnum|null $navigationGroup = 'Vouchers';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Receipt Voucher';

    protected static ?string $modelLabel = 'Receipt Voucher';

    protected static ?string $pluralModelLabel = 'Receipt Vouchers';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('voucher_type', VoucherType::Receipt->value);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->extraAttributes(['class' => 'sales-invoice-form payment-voucher-form'])
                ->schema([
                self::companySelect(),
                Hidden::make('voucher_type')->default(VoucherType::Receipt->value),
                Hidden::make('created_by')->default(fn (): ?int => auth()->id()),
                Grid::make([
                    'default' => 1,
                    'md' => 2,
                    'xl' => 6,
                ])->schema([
                    Grid::make(1)->schema([
                        Select::make('customer_id')
                            ->label('Customer')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required()
                            ->afterStateUpdated(fn (Set $set): null => self::resetAllocations($set)),
                        Placeholder::make('customer_balance')
                            ->label('Outstanding Balance')
                            ->content(fn (Get $get): string => self::customerBalance((int) ($get('customer_id') ?? 0)))
                            ->extraAttributes(['class' => 'sales-invoice-form__customer-balance']),
                    ])->columnSpan([
                        'default' => 1,
                        'xl' => 2,
                    ]),
                    Grid::make(1)->schema([
                        Select::make('bank_account_id')
                            ->label('Bank Account')
                            ->relationship('bankAccount', 'account_name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(),
                        Placeholder::make('bank_balance')
                            ->label('Current Balance')
                            ->content(fn (Get $get): string => self::bankBalance((int) ($get('bank_account_id') ?? 0)))
                            ->extraAttributes(['class' => 'sales-invoice-form__customer-balance']),
                    ]),
                    Grid::make(1)->schema([
                        DatePicker::make('voucher_date')
                            ->label('Receipt Date')
                            ->required()
                            ->default(now())
                            ->live()
                            ->afterStateUpdated(fn (Set $set, mixed $state, ?Voucher $record = null): null => self::syncVoucherNumber($set, $state, $record)),
                        TextInput::make('voucher_no')
                            ->label('Voucher No.')
                            ->default(fn (): string => self::nextVoucherNumber(now()))
                            ->disabled()
                            ->dehydrated(false),
                    ]),
                    Grid::make(1)->schema([
                        TextInput::make('reference_no')
                            ->label('Reference')
                            ->maxLength(255),
                        Select::make('status')
                            ->options(VoucherStatus::class)
                            ->default(VoucherStatus::Draft)
                            ->required(),
                    ]),
                    Grid::make(1)->schema([
                        self::moneyInput('amount')
                            ->label('Receipt Amount')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set): null => self::syncReceiptTotals($get, $set)),
                        Placeholder::make('total_receipt_display')
                            ->label('Total Receipt Amount')
                            ->content(fn (Get $get): string => self::formatMoney(self::currentReceiptAmount($get)))
                            ->extraAttributes(['class' => 'sales-invoice-form__amount-due']),
                    ]),
                ])->columnSpanFull(),
                Repeater::make('allocations')
                    ->label('Allocations')
                    ->relationship()
                    ->table([
                        TableColumn::make('Invoice No.')->alignment(Alignment::Center)->width('34%'),
                        TableColumn::make('Invoice Date')->alignment(Alignment::Center)->width('16%'),
                        TableColumn::make('Invoice Total')->alignment(Alignment::Center)->width('16%'),
                        TableColumn::make('Receipt Amount')->alignment(Alignment::Center)->width('18%'),
                        TableColumn::make('Remaining')->alignment(Alignment::Center)->width('16%'),
                    ])
                    ->schema([
                        Select::make('sales_invoice_id')
                            ->label('Sales Invoice')
                            ->hiddenLabel()
                            ->placeholder('Select invoice')
                            ->options(fn (Get $get, ?Voucher $record): array => self::salesInvoiceOptions(
                                (int) ($get('../../customer_id') ?? 0),
                                $record,
                                self::selectedSiblingInvoiceIds($get),
                            ))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                            ->disabled(fn (Get $get): bool => blank($get('../../customer_id')))
                            ->required()
                            ->afterStateUpdated(function (Get $get, Set $set, ?int $state): null {
                                if ($state !== null) {
                                    $set('amount', self::salesInvoiceOutstandingAmountById($state));
                                }

                                return self::syncReceiptTotals($get, $set, '../../');
                            })
                            ->extraAttributes(['class' => 'sales-invoice-form__description-cell']),
                        Placeholder::make('invoice_date_display')
                            ->hiddenLabel()
                            ->content(fn (Get $get): string => self::salesInvoiceDate((int) ($get('sales_invoice_id') ?? 0)))
                            ->extraAttributes(['class' => 'sales-invoice-form__line-total']),
                        Placeholder::make('invoice_total_display')
                            ->hiddenLabel()
                            ->content(fn (Get $get): string => self::salesInvoiceTotal((int) ($get('sales_invoice_id') ?? 0)))
                            ->extraAttributes(['class' => 'sales-invoice-form__line-total']),
                        self::moneyInput('amount')
                            ->hiddenLabel()
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set): null => self::syncReceiptTotals($get, $set, '../../'))
                            ->extraAttributes(['class' => 'sales-invoice-form__centered-field']),
                        Placeholder::make('remaining_display')
                            ->hiddenLabel()
                            ->content(fn (Get $get, ?Voucher $record): string => self::remainingAfterReceipt(
                                (int) ($get('sales_invoice_id') ?? 0),
                                (float) ($get('amount') ?? 0),
                                $record,
                            ))
                            ->extraAttributes(['class' => 'payment-voucher-form__remaining']),
                    ])
                    ->addActionLabel('Add Another Invoice')
                    ->addAction(fn (Action $action): Action => $action
                        ->icon(Heroicon::Plus)
                        ->button()
                        ->color('gray')
                        ->extraAttributes(['class' => 'sales-invoice-form__add-line']))
                    ->deleteAction(fn (Action $action): Action => $action
                        ->icon(Heroicon::Trash)
                        ->iconButton()
                        ->color('danger')
                        ->after(fn (Get $get, Set $set): null => self::syncReceiptTotals($get, $set)))
                    ->defaultItems(0)
                    ->reorderable(false)
                    ->compact()
                    ->extraAttributes(['class' => 'sales-invoice-form__lines payment-voucher-form__lines'])
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3)
                    ->columnSpanFull(),
            ])->columns(1)->columnSpanFull(),
        ]);
    }

    public static function calculateTotalsFromData(array $data): array
    {
        $allocationAmount = 0.0;
        $hasInvoiceAllocation = false;

        foreach (($data['allocations'] ?? []) as $allocation) {
            if (blank($allocation['sales_invoice_id'] ?? null)) {
                continue;
            }

            $hasInvoiceAllocation = true;
            $allocationAmount += (float) ($allocation['amount'] ?? 0);
        }

        $data['amount'] = round($hasInvoiceAllocation ? $allocationAmount : (float) ($data['amount'] ?? 0), 2);
        $data['allocations'] = collect($data['allocations'] ?? [])
            ->filter(fn (array $allocation): bool => filled($allocation['sales_invoice_id'] ?? null))
            ->values()
            ->all();

        return $data;
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
        return [
            'index' => ListReceiptVouchers::route('/'),
            'create' => CreateReceiptVoucher::route('/create'),
            'edit' => EditReceiptVoucher::route('/{record}/edit'),
        ];
    }

    private static function bankBalance(int $bankAccountId): string
    {
        $bankAccount = BankAccount::query()->find($bankAccountId);

        return $bankAccount ? app_money($bankAccount->currentBalance()) : 'Select a bank account';
    }

    private static function nextVoucherNumber(mixed $date = null): string
    {
        $companyId = auth()->user()?->company_id;

        return $companyId ? Voucher::nextVoucherNo($companyId, VoucherType::Receipt, $date) : '';
    }

    private static function syncVoucherNumber(Set $set, mixed $date = null, ?Voucher $record = null): null
    {
        if ($record !== null) {
            return null;
        }

        $set('voucher_no', self::nextVoucherNumber($date));

        return null;
    }

    private static function resetAllocations(Set $set): null
    {
        $set('allocations', []);
        $set('amount', 0);

        return null;
    }

    private static function salesInvoiceOptions(int $customerId, ?Voucher $voucher = null, array $excludedInvoiceIds = []): array
    {
        if ($customerId < 1) {
            return [];
        }

        return SalesInvoice::withoutGlobalScopes()
            ->where('customer_id', $customerId)
            ->when($excludedInvoiceIds !== [], fn (Builder $query): Builder => $query->whereNotIn('id', $excludedInvoiceIds))
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (SalesInvoice $invoice): bool => self::salesInvoiceOutstandingAmount($invoice, $voucher) > 0)
            ->mapWithKeys(fn (SalesInvoice $invoice): array => [
                $invoice->id => $invoice->invoice_no.' - '.self::formatMoney(self::salesInvoiceOutstandingAmount($invoice, $voucher)).' due',
            ])
            ->all();
    }

    private static function salesInvoiceDate(int $invoiceId): string
    {
        $invoice = SalesInvoice::withoutGlobalScopes()->find($invoiceId);

        return $invoice?->invoice_date?->format('d/m/Y') ?? '-';
    }

    private static function salesInvoiceTotal(int $invoiceId): string
    {
        $invoice = SalesInvoice::withoutGlobalScopes()->find($invoiceId);

        return $invoice ? self::formatMoney((float) $invoice->total) : self::formatMoney(0);
    }

    private static function salesInvoiceOutstandingAmountById(int $invoiceId, ?Voucher $voucher = null): float
    {
        $invoice = SalesInvoice::withoutGlobalScopes()->find($invoiceId);

        return $invoice ? self::salesInvoiceOutstandingAmount($invoice, $voucher) : 0.0;
    }

    private static function salesInvoiceOutstandingAmount(SalesInvoice $invoice, ?Voucher $voucher = null): float
    {
        $receipts = (float) $invoice->allocations()
            ->whereHas('voucher', function (Builder $query) use ($voucher): void {
                $query->where('voucher_type', VoucherType::Receipt->value)
                    ->where('status', VoucherStatus::Posted->value);

                if ($voucher?->exists) {
                    $query->where('id', '!=', $voucher->id);
                }
            })
            ->sum('amount');

        $returns = (float) SalesReturn::withoutGlobalScopes()
            ->where('sales_invoice_id', $invoice->id)
            ->sum('total');

        return round(max(0, (float) $invoice->total - $returns - $receipts), 2);
    }

    private static function remainingAfterReceipt(int $invoiceId, float $receiptAmount, ?Voucher $voucher = null): string
    {
        return self::formatMoney(max(0, self::salesInvoiceOutstandingAmountById($invoiceId, $voucher) - $receiptAmount));
    }

    private static function syncReceiptTotals(Get $get, Set $set, string $parentPath = ''): null
    {
        $data = self::calculateTotalsFromData([
            'amount' => $get($parentPath.'amount'),
            'allocations' => (array) ($get($parentPath.'allocations') ?? []),
        ]);

        $set($parentPath.'amount', $data['amount']);

        return null;
    }

    private static function currentReceiptAmount(Get $get): float
    {
        return (float) self::calculateTotalsFromData([
            'amount' => $get('amount'),
            'allocations' => (array) ($get('allocations') ?? []),
        ])['amount'];
    }

    private static function selectedSiblingInvoiceIds(Get $get): array
    {
        $currentInvoiceId = (int) ($get('sales_invoice_id') ?? 0);

        return collect((array) ($get('../../allocations') ?? []))
            ->pluck('sales_invoice_id')
            ->filter()
            ->map(fn (mixed $invoiceId): int => (int) $invoiceId)
            ->reject(fn (int $invoiceId): bool => $invoiceId === $currentInvoiceId)
            ->unique()
            ->values()
            ->all();
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

    private static function formatMoney(float $amount): string
    {
        return app_money($amount);
    }
}
