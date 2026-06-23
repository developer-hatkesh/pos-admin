<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentVouchers;

use App\Enums\InvoiceStatus;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\PaymentVouchers\Pages\CreatePaymentVoucher;
use App\Filament\Resources\PaymentVouchers\Pages\EditPaymentVoucher;
use App\Filament\Resources\PaymentVouchers\Pages\ListPaymentVouchers;
use App\Models\BankAccount;
use App\Models\Expense;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use UnitEnum;

class PaymentVoucherResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = Voucher::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Vouchers';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Payment Voucher';

    protected static ?string $modelLabel = 'Payment Voucher';

    protected static ?string $pluralModelLabel = 'Payment Vouchers';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('voucher_type', VoucherType::Payment->value);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->extraAttributes(['class' => 'sales-invoice-form payment-voucher-form'])
                ->schema([
                    self::companySelect(),
                    Hidden::make('voucher_type')->default(VoucherType::Payment->value),
                    Hidden::make('created_by')->default(fn (): ?int => auth()->id()),
                    Grid::make([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 6,
                    ])->schema([
                        Grid::make(1)->schema([
                            Select::make('supplier_id')
                                ->label('Supplier')
                                ->placeholder('Search for a supplier')
                                ->relationship('supplier', 'name')
                                ->searchable()
                                ->preload()
                                ->live()
                                ->required()
                                ->afterStateUpdated(function (Set $set): void {
                                    $set('allocations', []);
                                }),
                            Placeholder::make('supplier_bank_display')
                                ->label('Supplier Bank Details')
                                ->content(fn (Get $get): HtmlString => self::supplierBankDisplay((int) ($get('supplier_id') ?? 0)))
                                ->extraAttributes(['class' => 'sales-invoice-form__customer-address']),
                        ])->columnSpan([
                            'default' => 1,
                            'xl' => 2,
                        ]),
                        Grid::make(1)->schema([
                            Select::make('bank_account_id')
                                ->label('Bank Account')
                                ->options(fn (): array => self::bankAccountOptions())
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
                                ->label('Payment Date')
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
                            Select::make('status')
                                ->options(fn (): array => self::paymentStatusOptions())
                                ->default(VoucherStatus::Posted->value)
                                ->required(),
                            TextInput::make('reference_no')
                                ->label('Reference')
                                ->placeholder('e.g. Payment for invoices')
                                ->maxLength(255),
                        ]),
                        Grid::make(1)->schema([
                            self::moneyInput('amount')
                                ->label('Payment Amount')
                                ->required()
                                ->live(onBlur: true)
                                ->helperText('Enter manually for payments without invoices. Invoice rows will auto-calculate this value.')
                                ->afterStateUpdated(fn (Get $get, Set $set): null => self::syncPaymentTotals($get, $set)),
                            Placeholder::make('total_payment_display')
                                ->label('Total Payment Amount (Auto Calculated)')
                                ->content(fn (Get $get): string => self::formatMoney(self::currentPaymentAmount($get)))
                                ->extraAttributes(['class' => 'sales-invoice-form__amount-due']),
                            Placeholder::make('supplier_balance')
                                ->label('Outstanding Balance')
                                ->content(fn (Get $get): string => self::supplierBalance((int) ($get('supplier_id') ?? 0)))
                                ->extraAttributes(['class' => 'sales-invoice-form__customer-balance']),
                        ]),
                    ])->columnSpanFull(),
                    Repeater::make('allocations')
                        ->label('Invoices')
                        ->relationship()
                        ->table([
                            TableColumn::make('Invoice No.')->alignment(Alignment::Center)->width('28%'),
                            TableColumn::make('Invoice Date')->alignment(Alignment::Center)->width('16%'),
                            TableColumn::make('Bill Amount')->alignment(Alignment::Center)->width('16%'),
                            TableColumn::make('Pay Amount')->alignment(Alignment::Center)->width('20%'),
                            TableColumn::make('Remaining')->alignment(Alignment::Center)->width('16%'),
                        ])
                        ->schema([
                            Select::make('purchase_invoice_id')
                                ->label('Purchase Invoice')
                                ->hiddenLabel()
                                ->placeholder('Select invoice')
                                ->options(fn (Get $get, ?Voucher $record): array => self::purchaseInvoiceOptions((int) ($get('../../supplier_id') ?? 0), $record))
                                ->searchable()
                                ->preload()
                                ->live()
                                ->disabled(fn (Get $get): bool => blank($get('../../supplier_id')))
                                ->afterStateUpdated(function (Get $get, Set $set, ?int $state): null {
                                    $set('expense_id', null);

                                    if ($state !== null) {
                                        $set('amount', self::purchaseInvoiceOutstandingAmountById($state));
                                    }

                                    return self::syncPaymentTotals($get, $set, '../../');
                                })
                                ->extraAttributes(['class' => 'sales-invoice-form__description-cell']),
                            Placeholder::make('invoice_date_display')
                                ->hiddenLabel()
                                ->content(fn (Get $get): string => self::purchaseInvoiceDate((int) ($get('purchase_invoice_id') ?? 0)))
                                ->extraAttributes(['class' => 'sales-invoice-form__line-total']),
                            Placeholder::make('invoice_total_display')
                                ->hiddenLabel()
                                ->content(fn (Get $get): string => self::purchaseInvoiceTotal((int) ($get('purchase_invoice_id') ?? 0)))
                                ->extraAttributes(['class' => 'sales-invoice-form__line-total']),
                            self::moneyInput('amount')
                                ->hiddenLabel()
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Get $get, Set $set): null => self::syncPaymentTotals($get, $set, '../../'))
                                ->extraAttributes(['class' => 'sales-invoice-form__centered-field']),
                            Placeholder::make('remaining_display')
                                ->hiddenLabel()
                                ->content(fn (Get $get, ?Voucher $record): string => self::remainingAfterPayment(
                                    (int) ($get('purchase_invoice_id') ?? 0),
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
                            ->after(fn (Get $get, Set $set): null => self::syncPaymentTotals($get, $set)))
                        ->defaultItems(0)
                        ->reorderable(false)
                        ->compact()
                        ->extraAttributes(['class' => 'sales-invoice-form__lines payment-voucher-form__lines'])
                        ->columnSpanFull(),
                    Grid::make([
                        'default' => 1,
                        'lg' => 2,
                    ])->schema([
                        Textarea::make('notes')
                            ->label('Notes')
                            ->placeholder('Add any notes here...')
                            ->rows(7)
                            ->maxLength(300)
                            ->columnSpan(1),
                        Grid::make(1)->schema([
                            Placeholder::make('summary_total_payment')
                                ->label('Total Payment Amount')
                                ->inlineLabel()
                                ->content(fn (Get $get): string => self::formatMoney(self::currentPaymentAmount($get)))
                                ->extraAttributes(['class' => 'sales-invoice-form__total-due']),
                            Placeholder::make('summary_total_invoices')
                                ->label('Total Invoices')
                                ->inlineLabel()
                                ->content(fn (Get $get): string => (string) self::selectedInvoiceCount($get)),
                            Placeholder::make('summary_total_bill_amount')
                                ->label('Total Bill Amount')
                                ->inlineLabel()
                                ->content(fn (Get $get): string => self::formatMoney(self::selectedInvoiceTotal($get))),
                            Placeholder::make('summary_balance_pending')
                                ->label('Balance Pending (Remaining)')
                                ->inlineLabel()
                                ->content(fn (Get $get): string => self::formatMoney(self::selectedInvoiceRemaining($get)))
                                ->extraAttributes(['class' => 'payment-voucher-form__summary-pending']),
                        ])->extraAttributes(['class' => 'sales-invoice-form__totals payment-voucher-form__summary']),
                    ])->columnSpanFull(),
                ])->columns(1)->columnSpanFull(),
        ]);
    }

    public static function calculateTotalsFromData(array $data): array
    {
        $allocationAmount = 0.0;
        $hasInvoiceAllocation = false;

        foreach (($data['allocations'] ?? []) as $allocation) {
            if (blank($allocation['purchase_invoice_id'] ?? null)) {
                continue;
            }

            $hasInvoiceAllocation = true;
            $allocationAmount += (float) ($allocation['amount'] ?? 0);
        }

        $data['amount'] = round($hasInvoiceAllocation ? $allocationAmount : (float) ($data['amount'] ?? 0), 2);
        $data['allocations'] = collect($data['allocations'] ?? [])
            ->filter(fn (array $allocation): bool => filled($allocation['purchase_invoice_id'] ?? null))
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
                TextColumn::make('supplier.name')->searchable(),
                TextColumn::make('amount')->formatStateUsing(fn (mixed $state): string => app_money($state))->sortable(),
                TextColumn::make('status')->badge()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(fn (): array => self::paymentStatusOptions()),
                self::dateRangeFilter('voucher_date'),
            ])
            ->defaultSort('voucher_date', 'desc')
            ->recordActions([
                Action::make('post')
                    ->icon(Heroicon::CheckCircle)
                    ->requiresConfirmation()
                    ->visible(fn (Voucher $record): bool => $record->status === VoucherStatus::Draft)
                    ->action(function (Voucher $record): void {
                        app(VoucherPostingService::class)->post($record);
                        Notification::make()->title('Payment voucher posted')->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentVouchers::route('/'),
            'create' => CreatePaymentVoucher::route('/create'),
            'edit' => EditPaymentVoucher::route('/{record}/edit'),
        ];
    }

    private static function bankBalance(int $bankAccountId): string
    {
        $bankAccount = BankAccount::query()->find($bankAccountId);

        return $bankAccount ? app_money($bankAccount->currentBalance()) : 'Select a bank account';
    }

    private static function paymentStatusOptions(): array
    {
        return [
            VoucherStatus::Posted->value => 'Posted',
            VoucherStatus::Cancelled->value => 'Cancelled',
        ];
    }

    private static function bankAccountOptions(): array
    {
        return BankAccount::query()
            ->orderBy('account_name')
            ->get()
            ->mapWithKeys(fn (BankAccount $account): array => [
                $account->id => trim($account->account_name.' - '.$account->bank_name),
            ])
            ->all();
    }

    private static function supplierBankDisplay(int $supplierId): HtmlString
    {
        if ($supplierId < 1) {
            return new HtmlString('<span class="text-gray-500">Select a supplier to view bank details</span>');
        }

        $supplier = Supplier::query()->find($supplierId);

        if (! $supplier) {
            return new HtmlString('<span class="text-gray-500">Supplier not found</span>');
        }

        return new HtmlString(filled($supplier->bank_name)
            ? e($supplier->bank_name)
            : '<span class="text-gray-500">No supplier bank details saved</span>');
    }

    private static function purchaseInvoiceOptions(int $supplierId, ?Voucher $voucher = null): array
    {
        if ($supplierId < 1) {
            return [];
        }

        return PurchaseInvoice::withoutGlobalScopes()
            ->where('supplier_id', $supplierId)
            ->whereIn('status', [
                InvoiceStatus::Draft->value,
                InvoiceStatus::Posted->value,
                InvoiceStatus::Partial->value,
            ])
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (PurchaseInvoice $invoice): bool => self::purchaseInvoiceOutstandingAmount($invoice, $voucher) > 0)
            ->mapWithKeys(fn (PurchaseInvoice $invoice): array => [
                $invoice->id => $invoice->invoice_no.' - '.self::formatMoney(self::purchaseInvoiceOutstandingAmount($invoice, $voucher)).' due',
            ])
            ->all();
    }

    private static function purchaseInvoiceDate(int $invoiceId): string
    {
        $invoice = PurchaseInvoice::withoutGlobalScopes()->find($invoiceId);

        return $invoice?->invoice_date?->format('d/m/Y') ?? '-';
    }

    private static function purchaseInvoiceTotal(int $invoiceId): string
    {
        $invoice = PurchaseInvoice::withoutGlobalScopes()->find($invoiceId);

        return $invoice ? self::formatMoney((float) $invoice->total) : self::formatMoney(0);
    }

    private static function purchaseInvoiceOutstandingAmountById(int $invoiceId, ?Voucher $voucher = null): float
    {
        $invoice = PurchaseInvoice::withoutGlobalScopes()->find($invoiceId);

        return $invoice ? self::purchaseInvoiceOutstandingAmount($invoice, $voucher) : 0.0;
    }

    private static function purchaseInvoiceOutstandingAmount(PurchaseInvoice $invoice, ?Voucher $voucher = null): float
    {
        $paid = (float) $invoice->allocations()
            ->whereHas('voucher', function (Builder $query) use ($voucher): void {
                $query->where('status', VoucherStatus::Posted->value);

                if ($voucher?->exists) {
                    $query->where('id', '!=', $voucher->id);
                }
            })
            ->sum('amount');

        return round(max(0, (float) $invoice->total - $paid), 2);
    }

    private static function remainingAfterPayment(int $invoiceId, float $payAmount, ?Voucher $voucher = null): string
    {
        return self::formatMoney(max(0, self::purchaseInvoiceOutstandingAmountById($invoiceId, $voucher) - $payAmount));
    }

    private static function syncPaymentTotals(Get $get, Set $set, string $parentPath = ''): null
    {
        $data = self::calculateTotalsFromData([
            'amount' => $get($parentPath.'amount'),
            'allocations' => (array) ($get($parentPath.'allocations') ?? []),
        ]);

        $set($parentPath.'amount', $data['amount']);

        return null;
    }

    private static function currentPaymentAmount(Get $get): float
    {
        return (float) self::calculateTotalsFromData([
            'amount' => $get('amount'),
            'allocations' => (array) ($get('allocations') ?? []),
        ])['amount'];
    }

    private static function selectedInvoiceCount(Get $get): int
    {
        return collect((array) ($get('allocations') ?? []))
            ->filter(fn (array $allocation): bool => filled($allocation['purchase_invoice_id'] ?? null))
            ->count();
    }

    private static function selectedInvoiceTotal(Get $get): float
    {
        $invoiceIds = collect((array) ($get('allocations') ?? []))
            ->pluck('purchase_invoice_id')
            ->filter()
            ->unique()
            ->values();

        if ($invoiceIds->isEmpty()) {
            return 0.0;
        }

        return round((float) PurchaseInvoice::withoutGlobalScopes()
            ->whereIn('id', $invoiceIds)
            ->sum('total'), 2);
    }

    private static function selectedInvoiceRemaining(Get $get): float
    {
        $remaining = 0.0;

        foreach ((array) ($get('allocations') ?? []) as $allocation) {
            $invoiceId = (int) ($allocation['purchase_invoice_id'] ?? 0);

            if ($invoiceId < 1) {
                continue;
            }

            $remaining += max(0, self::purchaseInvoiceOutstandingAmountById($invoiceId) - (float) ($allocation['amount'] ?? 0));
        }

        return round($remaining, 2);
    }

    private static function nextVoucherNumber(mixed $date = null): string
    {
        $companyId = auth()->user()?->company_id;

        return $companyId ? Voucher::nextVoucherNo($companyId, VoucherType::Payment, $date) : '';
    }

    private static function syncVoucherNumber(Set $set, mixed $date = null, ?Voucher $record = null): null
    {
        if ($record !== null) {
            return null;
        }

        $set('voucher_no', self::nextVoucherNumber($date));

        return null;
    }

    private static function supplierBalance(int $supplierId): string
    {
        $supplier = Supplier::query()->find($supplierId);

        if (! $supplier) {
            return 'Select a supplier';
        }

        $purchases = (float) PurchaseInvoice::withoutGlobalScopes()->where('supplier_id', $supplierId)->sum('total');
        $expenses = (float) Expense::withoutGlobalScopes()->where('supplier_id', $supplierId)->sum('grand_total_amount');
        $payments = (float) Voucher::withoutGlobalScopes()
            ->where('voucher_type', VoucherType::Payment->value)
            ->where('supplier_id', $supplierId)
            ->where('status', VoucherStatus::Posted->value)
            ->sum('amount');

        return app_money(round((float) $supplier->opening_balance + $purchases + $expenses - $payments, 2));
    }

    private static function formatMoney(float $amount): string
    {
        return app_money($amount);
    }
}
