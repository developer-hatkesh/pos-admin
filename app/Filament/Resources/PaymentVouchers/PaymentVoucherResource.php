<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentVouchers;

use App\Enums\InvoiceStatus;
use App\Enums\ExpenseStatus;
use App\Enums\SalesReturnStatus;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\PaymentVouchers\Pages\CreatePaymentVoucher;
use App\Filament\Resources\PaymentVouchers\Pages\EditPaymentVoucher;
use App\Filament\Resources\PaymentVouchers\Pages\ListPaymentVouchers;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\PurchaseInvoice;
use App\Models\SalesReturn;
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
                    Hidden::make('status')->default(VoucherStatus::Posted->value),
                    Hidden::make('created_by')->default(fn (): ?int => auth()->id()),
                    Grid::make([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 6,
                    ])->schema([
                        Grid::make(1)->schema([
                            Select::make('payment_voucher_type')
                                ->label('Voucher Type')
                                ->options(self::paymentVoucherTypeOptions())
                                ->default('purchase')
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Set $set): void {
                                    $set('supplier_id', null);
                                    $set('customer_id', null);
                                    $set('allocations', []);
                                    $set('amount', 0);
                                }),
                            Select::make('supplier_id')
                                ->label('Supplier')
                                ->placeholder('Search for a supplier')
                                ->relationship('supplier', 'name')
                                ->searchable()
                                ->preload()
                                ->live()
                                ->required(fn (Get $get): bool => self::paymentVoucherType($get) === 'purchase')
                                ->visible(fn (Get $get): bool => in_array(self::paymentVoucherType($get), ['purchase', 'expense'], true))
                                ->afterStateUpdated(function (Set $set): void {
                                    $set('allocations', []);
                                    $set('amount', 0);
                                }),
                            Select::make('customer_id')
                                ->label('Customer')
                                ->placeholder('Search for a customer')
                                ->relationship('customer', 'name')
                                ->searchable()
                                ->preload()
                                ->live()
                                ->required(false)
                                ->visible(fn (Get $get): bool => self::paymentVoucherType($get) === 'credit_note')
                                ->afterStateUpdated(function (Set $set): void {
                                    $set('allocations', []);
                                    $set('amount', 0);
                                }),
                            Placeholder::make('supplier_bank_display')
                                ->label(fn (Get $get): string => self::paymentVoucherType($get) === 'credit_note' ? 'Customer Details' : 'Supplier Bank Details')
                                ->content(fn (Get $get): HtmlString => self::partyDetailsDisplay($get))
                                ->extraAttributes(['class' => 'sales-invoice-form__customer-address']),
                            Placeholder::make('supplier_balance')
                                ->label('Outstanding Balance')
                                ->content(fn (Get $get, ?Voucher $record): string => self::currentOutstandingBalance($get, $record))
                                ->extraAttributes(['class' => 'sales-invoice-form__customer-balance']),
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
                                ->helperText('Selected rows will auto-calculate this value.')
                                ->afterStateUpdated(fn (Get $get, Set $set): null => self::syncPaymentTotals($get, $set)),
                        ]),
                    ])->columnSpanFull(),
                    Repeater::make('allocations')
                        ->label('Invoices / Items')
                        ->relationship()
                        ->table([
                            TableColumn::make('Document')->alignment(Alignment::Center)->width('28%'),
                            TableColumn::make('Date')->alignment(Alignment::Center)->width('16%'),
                            TableColumn::make('Bill Amount')->alignment(Alignment::Center)->width('16%'),
                            TableColumn::make('Pay Amount')->alignment(Alignment::Center)->width('20%'),
                            TableColumn::make('Remaining')->alignment(Alignment::Center)->width('16%'),
                        ])
                        ->schema([
                            Select::make('purchase_invoice_id')
                                ->label('Purchase Invoice')
                                ->hiddenLabel()
                                ->placeholder('Select invoice')
                                ->options(fn (Get $get, ?Voucher $record): array => self::purchaseInvoiceOptions(
                                    (int) ($get('../../supplier_id') ?? 0),
                                    $record,
                                    self::selectedSiblingDocumentIds($get, 'purchase_invoice_id'),
                                ))
                                ->searchable()
                                ->preload()
                                ->live()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->visible(fn (Get $get): bool => self::paymentVoucherType($get, '../../') === 'purchase')
                                ->disabled(fn (Get $get): bool => blank($get('../../supplier_id')))
                                ->afterStateUpdated(function (Get $get, Set $set, ?int $state): null {
                                    $set('expense_id', null);
                                    $set('sales_return_id', null);

                                    if ($state !== null) {
                                        $set('amount', self::purchaseInvoiceOutstandingAmountById($state));
                                    }

                                    return self::syncAllocationPaymentTotals($get, $set);
                                })
                                ->extraAttributes(['class' => 'sales-invoice-form__description-cell']),
                            Select::make('expense_id')
                                ->label('Expense')
                                ->hiddenLabel()
                                ->placeholder('Select expense')
                                ->options(fn (Get $get, ?Voucher $record): array => self::expenseOptions(
                                    (int) ($get('../../supplier_id') ?? 0),
                                    $record,
                                    self::selectedSiblingDocumentIds($get, 'expense_id'),
                                ))
                                ->searchable()
                                ->preload()
                                ->live()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->visible(fn (Get $get): bool => self::paymentVoucherType($get, '../../') === 'expense')
                                ->afterStateUpdated(function (Get $get, Set $set, ?int $state): null {
                                    $set('purchase_invoice_id', null);
                                    $set('sales_return_id', null);

                                    if ($state !== null) {
                                        $expense = Expense::withoutGlobalScopes()->find($state);
                                        $set('../../supplier_id', $expense?->supplier_id);
                                        $set('amount', self::expenseOutstandingAmountById($state));
                                    }

                                    return self::syncAllocationPaymentTotals($get, $set);
                                })
                                ->extraAttributes(['class' => 'sales-invoice-form__description-cell']),
                            Select::make('sales_return_id')
                                ->label('Credit Note')
                                ->hiddenLabel()
                                ->placeholder('Select return')
                                ->options(fn (Get $get, ?Voucher $record): array => self::salesReturnOptions(
                                    (int) ($get('../../customer_id') ?? 0),
                                    $record,
                                    self::selectedSiblingDocumentIds($get, 'sales_return_id'),
                                ))
                                ->searchable()
                                ->preload()
                                ->live()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->visible(fn (Get $get): bool => self::paymentVoucherType($get, '../../') === 'credit_note')
                                ->afterStateUpdated(function (Get $get, Set $set, ?int $state): null {
                                    $set('purchase_invoice_id', null);
                                    $set('expense_id', null);

                                    if ($state !== null) {
                                        $return = SalesReturn::withoutGlobalScopes()->find($state);
                                        $set('../../customer_id', $return?->customer_id);
                                        $set('amount', self::salesReturnOutstandingAmountById($state));
                                    }

                                    return self::syncAllocationPaymentTotals($get, $set);
                                })
                                ->extraAttributes(['class' => 'sales-invoice-form__description-cell']),
                            Placeholder::make('document_date_display')
                                ->hiddenLabel()
                                ->content(fn (Get $get): string => self::selectedDocumentDate($get))
                                ->extraAttributes(['class' => 'sales-invoice-form__line-total']),
                            Placeholder::make('document_total_display')
                                ->hiddenLabel()
                                ->content(fn (Get $get): string => self::formatMoney(self::selectedDocumentTotal($get)))
                                ->extraAttributes(['class' => 'sales-invoice-form__line-total']),
                            self::moneyInput('amount')
                                ->hiddenLabel()
                                ->required()
                                ->maxValue(fn (Get $get, ?Voucher $record): float => self::selectedDocumentOutstandingAmount($get, $record))
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Get $get, Set $set, mixed $state, ?Voucher $record): null => self::syncAllocationPaymentTotals($get, $set, $record, $state))
                                ->extraAttributes(['class' => 'sales-invoice-form__centered-field']),
                            Placeholder::make('remaining_display')
                                ->hiddenLabel()
                                ->content(fn (Get $get, ?Voucher $record): string => self::remainingAfterPayment($get, $record))
                                ->extraAttributes(['class' => 'payment-voucher-form__remaining']),
                        ])
                        ->addActionLabel('Add Another Item')
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
                                ->label('Total Items')
                                ->inlineLabel()
                                ->content(fn (Get $get): string => (string) self::selectedInvoiceCount($get)),
                            Placeholder::make('summary_total_bill_amount')
                                ->label('Total Bill Amount')
                                ->inlineLabel()
                                ->content(fn (Get $get): string => self::formatMoney(self::selectedInvoiceTotal($get))),
                            Placeholder::make('summary_balance_pending')
                                ->label('Balance Pending (Remaining)')
                                ->inlineLabel()
                                ->content(fn (Get $get, ?Voucher $record): string => self::formatMoney(self::selectedInvoiceRemaining($get, $record)))
                                ->extraAttributes(['class' => 'payment-voucher-form__summary-pending']),
                        ])->extraAttributes(['class' => 'sales-invoice-form__totals payment-voucher-form__summary']),
                    ])->columnSpanFull(),
                ])->columns(1)->columnSpanFull(),
        ]);
    }

    public static function calculateTotalsFromData(array $data): array
    {
        $allocationAmount = 0.0;
        $hasAllocation = false;
        $type = (string) ($data['payment_voucher_type'] ?? 'purchase');

        foreach (($data['allocations'] ?? []) as $allocation) {
            if (! is_array($allocation)) {
                continue;
            }

            $allocation = self::normalizeAllocationForType($allocation, $type);

            if (! self::allocationHasDocument($allocation)) {
                continue;
            }

            $hasAllocation = true;
            $allocationAmount += self::cappedAllocationAmount($allocation);
        }

        $data['amount'] = round($hasAllocation ? $allocationAmount : (float) ($data['amount'] ?? 0), 2);
        $data['allocations'] = collect($data['allocations'] ?? [])
            ->filter(fn (mixed $allocation): bool => is_array($allocation))
            ->map(fn (array $allocation): array => self::normalizeAllocationForType($allocation, $type))
            ->filter(fn (array $allocation): bool => self::allocationHasDocument($allocation))
            ->map(function (array $allocation): array {
                $allocation['amount'] = self::cappedAllocationAmount($allocation);

                return $allocation;
            })
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

    private static function paymentVoucherTypeOptions(): array
    {
        return [
            'expense' => 'Expense',
            'credit_note' => 'Credit Note',
            'purchase' => 'Purchase',
        ];
    }

    private static function paymentVoucherType(Get $get, string $parentPath = ''): string
    {
        $type = (string) ($get($parentPath.'payment_voucher_type') ?? 'purchase');

        return array_key_exists($type, self::paymentVoucherTypeOptions()) ? $type : 'purchase';
    }

    private static function allocationHasDocument(array $allocation): bool
    {
        return filled($allocation['purchase_invoice_id'] ?? null)
            || filled($allocation['expense_id'] ?? null)
            || filled($allocation['sales_return_id'] ?? null);
    }

    private static function normalizeAllocationForType(array $allocation, string $type): array
    {
        if ($type !== 'purchase') {
            $allocation['purchase_invoice_id'] = null;
        }

        if ($type !== 'expense') {
            $allocation['expense_id'] = null;
        }

        if ($type !== 'credit_note') {
            $allocation['sales_return_id'] = null;
        }

        return $allocation;
    }

    private static function cappedAllocationAmount(array $allocation): float
    {
        $amount = round((float) ($allocation['amount'] ?? 0), 2);
        $documentTotal = self::allocationDocumentTotal($allocation);

        return $documentTotal > 0 ? min($amount, $documentTotal) : $amount;
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

    private static function partyDetailsDisplay(Get $get): HtmlString
    {
        if (self::paymentVoucherType($get) === 'credit_note') {
            return self::customerDetailsDisplay((int) ($get('customer_id') ?? 0));
        }

        return self::supplierBankDisplay((int) ($get('supplier_id') ?? 0));
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

    private static function customerDetailsDisplay(int $customerId): HtmlString
    {
        if ($customerId < 1) {
            return new HtmlString('<span class="text-gray-500">Select a customer to view details</span>');
        }

        $customer = Customer::query()->find($customerId);

        if (! $customer) {
            return new HtmlString('<span class="text-gray-500">Customer not found</span>');
        }

        $details = collect([$customer->name, $customer->phone, $customer->email])
            ->filter()
            ->map(fn (string $line): string => e($line))
            ->implode('<br>');

        return new HtmlString($details !== '' ? $details : '<span class="text-gray-500">No customer details saved</span>');
    }

    private static function purchaseInvoiceOptions(int $supplierId, ?Voucher $voucher = null, array $excludedInvoiceIds = []): array
    {
        if ($supplierId < 1) {
            return [];
        }

        return PurchaseInvoice::withoutGlobalScopes()
            ->where('supplier_id', $supplierId)
            ->when($excludedInvoiceIds !== [], fn (Builder $query): Builder => $query->whereNotIn('id', $excludedInvoiceIds))
            ->whereIn('status', [
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

    private static function expenseOptions(int $supplierId, ?Voucher $voucher = null, array $excludedExpenseIds = []): array
    {
        return Expense::withoutGlobalScopes()
            ->when($excludedExpenseIds !== [], fn (Builder $query): Builder => $query->whereNotIn('id', $excludedExpenseIds))
            ->where('status', ExpenseStatus::Posted->value)
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (Expense $expense): bool => self::expenseOutstandingAmount($expense, $voucher) > 0)
            ->mapWithKeys(fn (Expense $expense): array => [
                $expense->id => $expense->voucher_no.' - '.self::formatMoney(self::expenseOutstandingAmount($expense, $voucher)).' due',
            ])
            ->all();
    }

    private static function expenseOutstandingAmountById(int $expenseId, ?Voucher $voucher = null): float
    {
        $expense = Expense::withoutGlobalScopes()->find($expenseId);

        return $expense ? self::expenseOutstandingAmount($expense, $voucher) : 0.0;
    }

    private static function expenseOutstandingAmount(Expense $expense, ?Voucher $voucher = null): float
    {
        $paid = (float) $expense->allocations()
            ->whereHas('voucher', function (Builder $query) use ($voucher): void {
                $query->where('status', VoucherStatus::Posted->value);

                if ($voucher?->exists) {
                    $query->where('id', '!=', $voucher->id);
                }
            })
            ->sum('amount');

        return round(max(0, (float) $expense->grand_total_amount - $paid), 2);
    }

    private static function salesReturnOptions(int $customerId, ?Voucher $voucher = null, array $excludedReturnIds = []): array
    {
        return SalesReturn::withoutGlobalScopes()
            ->when($excludedReturnIds !== [], fn (Builder $query): Builder => $query->whereNotIn('id', $excludedReturnIds))
            ->where('status', SalesReturnStatus::Posted->value)
            ->orderByDesc('return_date')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (SalesReturn $return): bool => self::salesReturnOutstandingAmount($return, $voucher) > 0)
            ->mapWithKeys(fn (SalesReturn $return): array => [
                $return->id => $return->return_no.' - '.self::formatMoney(self::salesReturnOutstandingAmount($return, $voucher)).' due',
            ])
            ->all();
    }

    private static function salesReturnOutstandingAmountById(int $returnId, ?Voucher $voucher = null): float
    {
        $return = SalesReturn::withoutGlobalScopes()->find($returnId);

        return $return ? self::salesReturnOutstandingAmount($return, $voucher) : 0.0;
    }

    private static function salesReturnOutstandingAmount(SalesReturn $return, ?Voucher $voucher = null): float
    {
        $paid = (float) $return->allocations()
            ->whereHas('voucher', function (Builder $query) use ($voucher): void {
                $query->where('status', VoucherStatus::Posted->value);

                if ($voucher?->exists) {
                    $query->where('id', '!=', $voucher->id);
                }
            })
            ->sum('amount');

        return round(max(0, (float) $return->total - $paid), 2);
    }

    private static function remainingAfterPayment(Get $get, ?Voucher $voucher = null): string
    {
        return self::formatMoney(max(0, self::selectedDocumentOutstandingAmount($get, $voucher) - (float) ($get('amount') ?? 0)));
    }

    private static function syncPaymentTotals(Get $get, Set $set, string $parentPath = ''): null
    {
        $data = self::calculateTotalsFromData([
            'amount' => $get($parentPath.'amount'),
            'payment_voucher_type' => $get($parentPath.'payment_voucher_type'),
            'allocations' => (array) ($get($parentPath.'allocations') ?? []),
        ]);

        $set($parentPath.'amount', $data['amount']);

        return null;
    }

    private static function syncAllocationPaymentTotals(Get $get, Set $set, ?Voucher $voucher = null, mixed $state = null): null
    {
        $maxAmount = self::selectedDocumentOutstandingAmount($get, $voucher);
        $amount = round((float) ($state ?? $get('amount') ?? 0), 2);

        if ($maxAmount > 0 && $amount > $maxAmount) {
            $amount = $maxAmount;
        }

        $set('amount', $amount);

        return self::syncPaymentTotals($get, $set, '../../');
    }

    private static function currentPaymentAmount(Get $get): float
    {
        return (float) self::calculateTotalsFromData([
            'amount' => $get('amount'),
            'payment_voucher_type' => $get('payment_voucher_type'),
            'allocations' => (array) ($get('allocations') ?? []),
        ])['amount'];
    }

    private static function selectedInvoiceCount(Get $get): int
    {
        return collect((array) ($get('allocations') ?? []))
            ->filter(fn (array $allocation): bool => self::allocationHasDocument($allocation))
            ->count();
    }

    private static function selectedInvoiceTotal(Get $get): float
    {
        $total = 0.0;

        foreach ((array) ($get('allocations') ?? []) as $allocation) {
            $total += self::allocationDocumentTotal($allocation);
        }

        return round($total, 2);
    }

    private static function selectedInvoiceRemaining(Get $get, ?Voucher $voucher = null): float
    {
        $remaining = 0.0;

        foreach ((array) ($get('allocations') ?? []) as $allocation) {
            $remaining += max(0, self::allocationDocumentOutstanding($allocation, $voucher) - (float) ($allocation['amount'] ?? 0));
        }

        return round($remaining, 2);
    }

    private static function selectedSiblingDocumentIds(Get $get, string $field): array
    {
        $currentId = (int) ($get($field) ?? 0);

        return collect((array) ($get('../../allocations') ?? []))
            ->pluck($field)
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->reject(fn (int $id): bool => $id === $currentId)
            ->unique()
            ->values()
            ->all();
    }

    private static function selectedDocumentDate(Get $get): string
    {
        if (($invoiceId = (int) ($get('purchase_invoice_id') ?? 0)) > 0) {
            return PurchaseInvoice::withoutGlobalScopes()->find($invoiceId)?->invoice_date?->format('d/m/Y') ?? '-';
        }

        if (($expenseId = (int) ($get('expense_id') ?? 0)) > 0) {
            return Expense::withoutGlobalScopes()->find($expenseId)?->expense_date?->format('d/m/Y') ?? '-';
        }

        if (($returnId = (int) ($get('sales_return_id') ?? 0)) > 0) {
            return SalesReturn::withoutGlobalScopes()->find($returnId)?->return_date?->format('d/m/Y') ?? '-';
        }

        return '-';
    }

    private static function selectedDocumentTotal(Get $get): float
    {
        return self::allocationDocumentTotal([
            'purchase_invoice_id' => $get('purchase_invoice_id'),
            'expense_id' => $get('expense_id'),
            'sales_return_id' => $get('sales_return_id'),
        ]);
    }

    private static function selectedDocumentOutstandingAmount(Get $get, ?Voucher $voucher = null): float
    {
        return self::allocationDocumentOutstanding([
            'purchase_invoice_id' => $get('purchase_invoice_id'),
            'expense_id' => $get('expense_id'),
            'sales_return_id' => $get('sales_return_id'),
        ], $voucher);
    }

    private static function allocationDocumentTotal(array $allocation): float
    {
        if (($invoiceId = (int) ($allocation['purchase_invoice_id'] ?? 0)) > 0) {
            return round((float) PurchaseInvoice::withoutGlobalScopes()->whereKey($invoiceId)->value('total'), 2);
        }

        if (($expenseId = (int) ($allocation['expense_id'] ?? 0)) > 0) {
            return round((float) Expense::withoutGlobalScopes()->whereKey($expenseId)->value('grand_total_amount'), 2);
        }

        if (($returnId = (int) ($allocation['sales_return_id'] ?? 0)) > 0) {
            return round((float) SalesReturn::withoutGlobalScopes()->whereKey($returnId)->value('total'), 2);
        }

        return 0.0;
    }

    private static function allocationDocumentOutstanding(array $allocation, ?Voucher $voucher = null): float
    {
        if (($invoiceId = (int) ($allocation['purchase_invoice_id'] ?? 0)) > 0) {
            return self::purchaseInvoiceOutstandingAmountById($invoiceId, $voucher);
        }

        if (($expenseId = (int) ($allocation['expense_id'] ?? 0)) > 0) {
            return self::expenseOutstandingAmountById($expenseId, $voucher);
        }

        if (($returnId = (int) ($allocation['sales_return_id'] ?? 0)) > 0) {
            return self::salesReturnOutstandingAmountById($returnId, $voucher);
        }

        return 0.0;
    }

    private static function currentOutstandingBalance(Get $get, ?Voucher $voucher = null): string
    {
        if (self::selectedInvoiceCount($get) > 0) {
            return self::formatMoney(self::selectedInvoiceRemaining($get, $voucher));
        }

        if (self::paymentVoucherType($get) === 'credit_note') {
            $customerId = (int) ($get('customer_id') ?? 0);

            if ($customerId < 1) {
                return 'Select a customer';
            }

            return self::formatMoney(max(0, self::customerCreditBalanceAmount($customerId) - self::currentPaymentAmount($get)));
        }

        $supplierId = (int) ($get('supplier_id') ?? 0);

        if ($supplierId < 1) {
            return 'Select a supplier';
        }

        return self::formatMoney(max(0, self::supplierBalanceAmount($supplierId) - self::currentPaymentAmount($get)));
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
        if ($supplierId < 1) {
            return 'Select a supplier';
        }

        return app_money(self::supplierBalanceAmount($supplierId));
    }

    private static function supplierBalanceAmount(int $supplierId): float
    {
        $supplier = Supplier::query()->find($supplierId);

        if (! $supplier) {
            return 0.0;
        }

        $purchases = (float) PurchaseInvoice::withoutGlobalScopes()
            ->where('supplier_id', $supplierId)
            ->whereIn('status', [
                InvoiceStatus::Posted->value,
                InvoiceStatus::Partial->value,
                InvoiceStatus::Paid->value,
            ])
            ->sum('total');
        $expenses = (float) Expense::withoutGlobalScopes()->where('supplier_id', $supplierId)->sum('grand_total_amount');
        $payments = (float) Voucher::withoutGlobalScopes()
            ->where('voucher_type', VoucherType::Payment->value)
            ->where('supplier_id', $supplierId)
            ->where('status', VoucherStatus::Posted->value)
            ->sum('amount');

        return round((float) $supplier->opening_balance + $purchases + $expenses - $payments, 2);
    }

    private static function customerCreditBalanceAmount(int $customerId): float
    {
        $customer = Customer::query()->find($customerId);

        if (! $customer) {
            return 0.0;
        }

        $returns = (float) SalesReturn::withoutGlobalScopes()
            ->where('customer_id', $customerId)
            ->where('status', SalesReturnStatus::Posted->value)
            ->sum('total');

        $refunds = (float) Voucher::withoutGlobalScopes()
            ->where('voucher_type', VoucherType::Payment->value)
            ->where('customer_id', $customerId)
            ->where('status', VoucherStatus::Posted->value)
            ->sum('amount');

        return round($returns - $refunds, 2);
    }

    private static function formatMoney(float $amount): string
    {
        return app_money($amount);
    }
}
