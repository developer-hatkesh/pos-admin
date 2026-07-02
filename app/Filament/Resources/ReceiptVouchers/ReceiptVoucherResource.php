<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReceiptVouchers;

use App\Enums\IncomeStatus;
use App\Enums\PurchaseReturnStatus;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\ReceiptVouchers\Pages\CreateReceiptVoucher;
use App\Filament\Resources\ReceiptVouchers\Pages\EditReceiptVoucher;
use App\Filament\Resources\ReceiptVouchers\Pages\ListReceiptVouchers;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Income;
use App\Models\PurchaseReturn;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\Supplier;
use App\Models\Voucher;
use App\Models\VoucherAllocation;
use App\Services\Accounting\VoucherPostingService;
use App\Support\CurrentCompany;
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
use Illuminate\Validation\ValidationException;
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
                    Hidden::make('status')->default(VoucherStatus::Posted->value),
                    Hidden::make('created_by')->default(fn (): ?int => auth()->id()),
                    Grid::make([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 6,
                    ])->schema([
                        Grid::make(1)->schema([
                            Select::make('receipt_voucher_type')
                                ->label('Voucher Type')
                                ->options(self::receiptVoucherTypeOptions())
                                ->default('customer')
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Set $set): void {
                                    $set('customer_id', null);
                                    $set('supplier_id', null);
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
                                ->required(fn (Get $get): bool => self::receiptVoucherType($get) === 'customer')
                                ->visible(fn (Get $get): bool => self::receiptVoucherType($get) === 'customer')
                                ->afterStateUpdated(fn (Set $set): null => self::resetAllocations($set)),
                            Select::make('supplier_id')
                                ->label('Supplier')
                                ->placeholder('Search for a supplier')
                                ->relationship('supplier', 'name')
                                ->searchable()
                                ->preload()
                                ->live()
                                ->required(fn (Get $get): bool => self::receiptVoucherType($get) === 'purchase_return')
                                ->visible(fn (Get $get): bool => self::receiptVoucherType($get) === 'purchase_return')
                                ->afterStateUpdated(fn (Set $set): null => self::resetAllocations($set)),
                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                Placeholder::make('party_details')
                                    ->label(fn (Get $get): string => self::receiptVoucherType($get) === 'purchase_return' ? 'Supplier Details' : 'Customer Details')
                                    ->content(fn (Get $get): HtmlString => self::partyDetailsDisplay($get))
                                    ->visible(fn (Get $get): bool => in_array(self::receiptVoucherType($get), ['customer', 'purchase_return'], true))
                                    ->extraAttributes(['class' => 'sales-invoice-form__customer-address sales-invoice-form__readonly-placeholder']),
                                Placeholder::make('party_balance')
                                    ->label('Outstanding Balance')
                                    ->content(fn (Get $get, ?Voucher $record): string => self::currentOutstandingBalance($get, $record))
                                    ->extraAttributes(['class' => 'sales-invoice-form__customer-balance sales-invoice-form__readonly-placeholder']),
                            ]),
                        ])->columnSpan(['default' => 1, 'xl' => 2]),
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
                                ->extraAttributes(['class' => 'sales-invoice-form__customer-balance sales-invoice-form__readonly-placeholder']),
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
                                ->placeholder('e.g. Receipt reference')
                                ->maxLength(255),
                        ]),
                        Grid::make(1)->schema([
                            self::moneyInput('amount')
                                ->label('Receipt Amount')
                                ->required()
                                ->minValue(0.01)
                                ->live(onBlur: true)
                                ->helperText('Selected rows will auto-calculate this value.')
                                ->afterStateUpdated(fn (Get $get, Set $set): null => self::syncReceiptTotals($get, $set)),
                        ]),
                    ])->columnSpanFull(),
                    Repeater::make('allocations')
                        ->label('Invoices / Items')
                        ->relationship()
                        ->table([
                            TableColumn::make('Document')->alignment(Alignment::Center)->width('28%'),
                            TableColumn::make('Date')->alignment(Alignment::Center)->width('16%'),
                            TableColumn::make('Bill Amount')->alignment(Alignment::Center)->width('16%'),
                            TableColumn::make('Received Amount')->alignment(Alignment::Center)->width('20%'),
                            TableColumn::make('Remaining')->alignment(Alignment::Center)->width('16%'),
                        ])
                        ->schema(fn (Get $get): array => [
                            self::allocationDocumentSelect(self::allocationSchemaReceiptVoucherType($get)),
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
                                ->minValue(0.01)
                                ->maxValue(fn (Get $get, mixed $record): float => self::selectedDocumentOutstandingAmount($get, self::voucherFromEvaluatedRecord($record)))
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Get $get, Set $set, mixed $state, mixed $record): null => self::syncAllocationReceiptTotals($get, $set, self::voucherFromEvaluatedRecord($record), $state))
                                ->extraAttributes(['class' => 'sales-invoice-form__centered-field']),
                            Placeholder::make('remaining_display')
                                ->hiddenLabel()
                                ->content(fn (Get $get, mixed $record): string => self::remainingAfterReceipt($get, self::voucherFromEvaluatedRecord($record)))
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
                            ->after(fn (Get $get, Set $set): null => self::syncReceiptTotals($get, $set)))
                        ->defaultItems(0)
                        ->reorderable(false)
                        ->compact()
                        ->extraAttributes(['class' => 'sales-invoice-form__lines payment-voucher-form__lines'])
                        ->columnSpanFull(),
                    Grid::make(['default' => 1, 'lg' => 2])->schema([
                        Textarea::make('notes')
                            ->label('Notes')
                            ->placeholder('Add any notes here...')
                            ->rows(7)
                            ->maxLength(300)
                            ->columnSpan(1),
                        Grid::make(1)->schema([
                            Placeholder::make('summary_total_receipt')
                                ->label('Total Receipt Amount')
                                ->inlineLabel()
                                ->content(fn (Get $get): string => self::formatMoney(self::currentReceiptAmount($get)))
                                ->extraAttributes(['class' => 'sales-invoice-form__total-due']),
                            Placeholder::make('summary_total_items')
                                ->label('Total Items')
                                ->inlineLabel()
                                ->content(fn (Get $get): string => (string) self::selectedItemCount($get)),
                            Placeholder::make('summary_total_bill_amount')
                                ->label('Total Bill Amount')
                                ->inlineLabel()
                                ->content(fn (Get $get): string => self::formatMoney(self::selectedDocumentTotalSum($get))),
                            Placeholder::make('summary_balance_pending')
                                ->label('Balance Pending (Remaining)')
                                ->inlineLabel()
                                ->content(fn (Get $get, ?Voucher $record): string => self::formatMoney(self::selectedDocumentRemaining($get, $record)))
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
        $type = (string) ($data['receipt_voucher_type'] ?? 'customer');

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

        if ($type !== 'customer') {
            $data['customer_id'] = null;
        }

        if ($type !== 'purchase_return') {
            $data['supplier_id'] = null;
        }

        return $data;
    }

    public static function validatePostableData(array $data): void
    {
        if (round((float) ($data['amount'] ?? 0), 2) <= 0.0) {
            throw ValidationException::withMessages([
                'data.amount' => 'Receipt amount must be greater than zero.',
            ]);
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('voucher_no')->searchable()->sortable(),
                TextColumn::make('receipt_voucher_type')->label('Type')->formatStateUsing(fn (?string $state): string => self::receiptVoucherTypeOptions()[$state ?: 'customer'] ?? 'Customer')->sortable(),
                TextColumn::make('voucher_date')->date()->sortable(),
                TextColumn::make('bankAccount.account_name')->searchable(),
                TextColumn::make('customer.name')->searchable(),
                TextColumn::make('supplier.name')->searchable(),
                TextColumn::make('amount')->formatStateUsing(fn (mixed $state): string => app_money($state))->sortable(),
                TextColumn::make('status')->badge()->sortable(),
            ])
            ->filters([
                SelectFilter::make('receipt_voucher_type')->label('Type')->options(fn (): array => self::receiptVoucherTypeOptions()),
                self::statusFilter(VoucherStatus::class),
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

    private static function receiptVoucherTypeOptions(): array
    {
        return [
            'customer' => 'Customer',
            'purchase_return' => 'Purchase Return',
            'income' => 'Income',
        ];
    }

    private static function receiptVoucherType(Get $get, string $parentPath = ''): string
    {
        $type = (string) ($get($parentPath.'receipt_voucher_type') ?? 'customer');

        return array_key_exists($type, self::receiptVoucherTypeOptions()) ? $type : 'customer';
    }

    private static function allocationSchemaReceiptVoucherType(Get $get): string
    {
        foreach (['', '../', '../../'] as $path) {
            $type = (string) ($get($path.'receipt_voucher_type') ?? '');

            if (array_key_exists($type, self::receiptVoucherTypeOptions())) {
                return $type;
            }
        }

        return 'customer';
    }

    private static function allocationDocumentSelect(string $type): Select
    {
        if ($type === 'purchase_return') {
            return Select::make('purchase_return_id')
                ->label('Purchase Return')
                ->hiddenLabel()
                ->placeholder('Select purchase return')
                ->options(fn (Get $get, mixed $record): array => self::purchaseReturnOptions(
                    (int) ($get('../../supplier_id') ?? 0),
                    self::voucherFromEvaluatedRecord($record),
                    self::selectedSiblingDocumentIds($get, 'purchase_return_id'),
                ))
                ->searchable()
                ->preload()
                ->live()
                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                ->disabled(fn (Get $get): bool => blank($get('../../supplier_id')))
                ->required()
                ->afterStateUpdated(function (Get $get, Set $set, ?int $state): null {
                    $set('sales_invoice_id', null);
                    $set('income_id', null);

                    if ($state !== null) {
                        $return = PurchaseReturn::withoutGlobalScopes()->find($state);
                        $set('../../supplier_id', $return?->supplier_id);
                        $set('amount', self::purchaseReturnOutstandingAmountById($state));
                    }

                    return self::syncAllocationReceiptTotals($get, $set);
                })
                ->extraAttributes(['class' => 'sales-invoice-form__description-cell']);
        }

        if ($type === 'income') {
            return Select::make('income_id')
                ->label('Income')
                ->hiddenLabel()
                ->placeholder('Select posted income')
                ->options(fn (Get $get, mixed $record): array => self::incomeOptions(
                    self::voucherFromEvaluatedRecord($record),
                    self::selectedSiblingDocumentIds($get, 'income_id'),
                ))
                ->searchable()
                ->preload()
                ->live()
                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                ->required()
                ->afterStateUpdated(function (Get $get, Set $set, ?int $state): null {
                    $set('sales_invoice_id', null);
                    $set('purchase_return_id', null);

                    if ($state !== null) {
                        $set('amount', self::incomeOutstandingAmountById($state));
                    }

                    return self::syncAllocationReceiptTotals($get, $set);
                })
                ->extraAttributes(['class' => 'sales-invoice-form__description-cell']);
        }

        return Select::make('sales_invoice_id')
            ->label('Sales Invoice')
            ->hiddenLabel()
            ->placeholder('Select invoice')
            ->options(fn (Get $get, mixed $record): array => self::salesInvoiceOptions(
                (int) ($get('../../customer_id') ?? 0),
                self::voucherFromEvaluatedRecord($record),
                self::selectedSiblingDocumentIds($get, 'sales_invoice_id'),
            ))
            ->searchable()
            ->preload()
            ->live()
            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
            ->disabled(fn (Get $get): bool => blank($get('../../customer_id')))
            ->required()
            ->afterStateUpdated(function (Get $get, Set $set, ?int $state): null {
                $set('purchase_return_id', null);
                $set('income_id', null);

                if ($state !== null) {
                    $set('amount', self::salesInvoiceOutstandingAmountById($state));
                }

                return self::syncAllocationReceiptTotals($get, $set);
            })
            ->extraAttributes(['class' => 'sales-invoice-form__description-cell']);
    }

    private static function allocationHasDocument(array $allocation): bool
    {
        return filled($allocation['sales_invoice_id'] ?? null)
            || filled($allocation['purchase_return_id'] ?? null)
            || filled($allocation['income_id'] ?? null);
    }

    private static function normalizeAllocationForType(array $allocation, string $type): array
    {
        if ($type !== 'customer') {
            $allocation['sales_invoice_id'] = null;
        }

        if ($type !== 'purchase_return') {
            $allocation['purchase_return_id'] = null;
        }

        if ($type !== 'income') {
            $allocation['income_id'] = null;
        }

        return $allocation;
    }

    private static function cappedAllocationAmount(array $allocation): float
    {
        $amount = round((float) ($allocation['amount'] ?? 0), 2);
        $documentTotal = self::allocationDocumentTotal($allocation);

        return $documentTotal > 0 ? min($amount, $documentTotal) : $amount;
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

    private static function voucherFromEvaluatedRecord(mixed $record): ?Voucher
    {
        if ($record instanceof Voucher) {
            return $record;
        }

        if ($record instanceof VoucherAllocation) {
            return $record->voucher;
        }

        return null;
    }

    private static function bankBalance(int $bankAccountId): string
    {
        $bankAccount = BankAccount::query()->find($bankAccountId);

        return $bankAccount ? app_money($bankAccount->currentBalance()) : 'Select a bank account';
    }

    private static function partyDetailsDisplay(Get $get): HtmlString
    {
        if (self::receiptVoucherType($get) === 'purchase_return') {
            return self::supplierDetailsDisplay((int) ($get('supplier_id') ?? 0));
        }

        return self::customerDetailsDisplay((int) ($get('customer_id') ?? 0));
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

    private static function supplierDetailsDisplay(int $supplierId): HtmlString
    {
        if ($supplierId < 1) {
            return new HtmlString('<span class="text-gray-500">Select a supplier to view details</span>');
        }

        $supplier = Supplier::query()->find($supplierId);

        if (! $supplier) {
            return new HtmlString('<span class="text-gray-500">Supplier not found</span>');
        }

        $details = collect([$supplier->name, $supplier->phone, $supplier->email])
            ->filter()
            ->map(fn (string $line): string => e($line))
            ->implode('<br>');

        return new HtmlString($details !== '' ? $details : '<span class="text-gray-500">No supplier details saved</span>');
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

    private static function purchaseReturnOptions(int $supplierId, ?Voucher $voucher = null, array $excludedReturnIds = []): array
    {
        if ($supplierId < 1) {
            return [];
        }

        return PurchaseReturn::withoutGlobalScopes()
            ->where('supplier_id', $supplierId)
            ->when($excludedReturnIds !== [], fn (Builder $query): Builder => $query->whereNotIn('id', $excludedReturnIds))
            ->where('status', PurchaseReturnStatus::Posted->value)
            ->orderByDesc('return_date')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (PurchaseReturn $return): bool => self::purchaseReturnOutstandingAmount($return, $voucher) > 0)
            ->mapWithKeys(fn (PurchaseReturn $return): array => [
                $return->id => $return->return_no.' - '.self::formatMoney(self::purchaseReturnOutstandingAmount($return, $voucher)).' due',
            ])
            ->all();
    }

    private static function incomeOptions(?Voucher $voucher = null, array $excludedIncomeIds = []): array
    {
        return Income::withoutGlobalScopes()
            ->when($excludedIncomeIds !== [], fn (Builder $query): Builder => $query->whereNotIn('id', $excludedIncomeIds))
            ->where('status', IncomeStatus::Posted->value)
            ->orderByDesc('income_date')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (Income $income): bool => self::incomeOutstandingAmount($income, $voucher) > 0)
            ->mapWithKeys(fn (Income $income): array => [
                $income->id => $income->voucher_no.' - '.self::formatMoney(self::incomeOutstandingAmount($income, $voucher)).' due',
            ])
            ->all();
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

    private static function purchaseReturnOutstandingAmountById(int $returnId, ?Voucher $voucher = null): float
    {
        $return = PurchaseReturn::withoutGlobalScopes()->find($returnId);

        return $return ? self::purchaseReturnOutstandingAmount($return, $voucher) : 0.0;
    }

    private static function purchaseReturnOutstandingAmount(PurchaseReturn $return, ?Voucher $voucher = null): float
    {
        $received = (float) $return->allocations()
            ->whereHas('voucher', function (Builder $query) use ($voucher): void {
                $query->where('voucher_type', VoucherType::Receipt->value)
                    ->where('status', VoucherStatus::Posted->value);

                if ($voucher?->exists) {
                    $query->where('id', '!=', $voucher->id);
                }
            })
            ->sum('amount');

        return round(max(0, (float) $return->total - $received), 2);
    }

    private static function incomeOutstandingAmountById(int $incomeId, ?Voucher $voucher = null): float
    {
        $income = Income::withoutGlobalScopes()->find($incomeId);

        return $income ? self::incomeOutstandingAmount($income, $voucher) : 0.0;
    }

    private static function incomeOutstandingAmount(Income $income, ?Voucher $voucher = null): float
    {
        $received = (float) $income->allocations()
            ->whereHas('voucher', function (Builder $query) use ($voucher): void {
                $query->where('voucher_type', VoucherType::Receipt->value)
                    ->where('status', VoucherStatus::Posted->value);

                if ($voucher?->exists) {
                    $query->where('id', '!=', $voucher->id);
                }
            })
            ->sum('amount');

        return round(max(0, (float) $income->grand_total_amount - $received), 2);
    }

    private static function remainingAfterReceipt(Get $get, ?Voucher $voucher = null): string
    {
        return self::formatMoney(max(0, self::selectedDocumentOutstandingAmount($get, $voucher) - (float) ($get('amount') ?? 0)));
    }

    private static function syncReceiptTotals(Get $get, Set $set, string $parentPath = ''): null
    {
        $data = self::calculateTotalsFromData([
            'amount' => $get($parentPath.'amount'),
            'receipt_voucher_type' => $get($parentPath.'receipt_voucher_type'),
            'allocations' => (array) ($get($parentPath.'allocations') ?? []),
        ]);

        $set($parentPath.'amount', $data['amount']);

        return null;
    }

    private static function syncAllocationReceiptTotals(Get $get, Set $set, ?Voucher $voucher = null, mixed $state = null): null
    {
        $maxAmount = self::selectedDocumentOutstandingAmount($get, $voucher);
        $amount = round((float) ($state ?? $get('amount') ?? 0), 2);

        if ($maxAmount > 0 && $amount > $maxAmount) {
            $amount = $maxAmount;
        }

        $set('amount', $amount);

        return self::syncReceiptTotals($get, $set, '../../');
    }

    private static function currentReceiptAmount(Get $get): float
    {
        return (float) self::calculateTotalsFromData([
            'amount' => $get('amount'),
            'receipt_voucher_type' => $get('receipt_voucher_type'),
            'allocations' => (array) ($get('allocations') ?? []),
        ])['amount'];
    }

    private static function selectedItemCount(Get $get): int
    {
        return collect((array) ($get('allocations') ?? []))
            ->filter(fn (array $allocation): bool => self::allocationHasDocument($allocation))
            ->count();
    }

    private static function selectedDocumentTotalSum(Get $get): float
    {
        $total = 0.0;

        foreach ((array) ($get('allocations') ?? []) as $allocation) {
            $total += self::allocationDocumentTotal($allocation);
        }

        return round($total, 2);
    }

    private static function selectedDocumentRemaining(Get $get, ?Voucher $voucher = null): float
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
        if (($invoiceId = (int) ($get('sales_invoice_id') ?? 0)) > 0) {
            return SalesInvoice::withoutGlobalScopes()->find($invoiceId)?->invoice_date?->format('d/m/Y') ?? '-';
        }

        if (($returnId = (int) ($get('purchase_return_id') ?? 0)) > 0) {
            return PurchaseReturn::withoutGlobalScopes()->find($returnId)?->return_date?->format('d/m/Y') ?? '-';
        }

        if (($incomeId = (int) ($get('income_id') ?? 0)) > 0) {
            return Income::withoutGlobalScopes()->find($incomeId)?->income_date?->format('d/m/Y') ?? '-';
        }

        return '-';
    }

    private static function selectedDocumentTotal(Get $get): float
    {
        return self::allocationDocumentTotal([
            'sales_invoice_id' => $get('sales_invoice_id'),
            'purchase_return_id' => $get('purchase_return_id'),
            'income_id' => $get('income_id'),
        ]);
    }

    private static function selectedDocumentOutstandingAmount(Get $get, ?Voucher $voucher = null): float
    {
        return self::allocationDocumentOutstanding([
            'sales_invoice_id' => $get('sales_invoice_id'),
            'purchase_return_id' => $get('purchase_return_id'),
            'income_id' => $get('income_id'),
        ], $voucher);
    }

    private static function allocationDocumentTotal(array $allocation): float
    {
        if (($invoiceId = (int) ($allocation['sales_invoice_id'] ?? 0)) > 0) {
            return round((float) SalesInvoice::withoutGlobalScopes()->whereKey($invoiceId)->value('total'), 2);
        }

        if (($returnId = (int) ($allocation['purchase_return_id'] ?? 0)) > 0) {
            return round((float) PurchaseReturn::withoutGlobalScopes()->whereKey($returnId)->value('total'), 2);
        }

        if (($incomeId = (int) ($allocation['income_id'] ?? 0)) > 0) {
            return round((float) Income::withoutGlobalScopes()->whereKey($incomeId)->value('grand_total_amount'), 2);
        }

        return 0.0;
    }

    private static function allocationDocumentOutstanding(array $allocation, ?Voucher $voucher = null): float
    {
        if (($invoiceId = (int) ($allocation['sales_invoice_id'] ?? 0)) > 0) {
            return self::salesInvoiceOutstandingAmountById($invoiceId, $voucher);
        }

        if (($returnId = (int) ($allocation['purchase_return_id'] ?? 0)) > 0) {
            return self::purchaseReturnOutstandingAmountById($returnId, $voucher);
        }

        if (($incomeId = (int) ($allocation['income_id'] ?? 0)) > 0) {
            return self::incomeOutstandingAmountById($incomeId, $voucher);
        }

        return 0.0;
    }

    private static function currentOutstandingBalance(Get $get, ?Voucher $voucher = null): string
    {
        if (self::selectedItemCount($get) > 0) {
            return self::formatMoney(self::selectedDocumentRemaining($get, $voucher));
        }

        if (self::receiptVoucherType($get) === 'purchase_return') {
            $supplierId = (int) ($get('supplier_id') ?? 0);

            if ($supplierId < 1) {
                return 'Select a supplier';
            }

            return self::formatMoney(self::supplierPurchaseReturnBalanceAmount($supplierId));
        }

        if (self::receiptVoucherType($get) === 'income') {
            return self::formatMoney((float) Income::withoutGlobalScopes()->where('status', IncomeStatus::Posted->value)->sum('grand_total_amount'));
        }

        $customerId = (int) ($get('customer_id') ?? 0);

        if ($customerId < 1) {
            return 'Select a customer';
        }

        return self::customerBalance($customerId);
    }

    private static function nextVoucherNumber(mixed $date = null): string
    {
        $companyId = app(CurrentCompany::class)->id();

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

    private static function supplierPurchaseReturnBalanceAmount(int $supplierId): float
    {
        $returns = (float) PurchaseReturn::withoutGlobalScopes()
            ->where('supplier_id', $supplierId)
            ->where('status', PurchaseReturnStatus::Posted->value)
            ->sum('total');
        $receipts = (float) Voucher::withoutGlobalScopes()
            ->where('voucher_type', VoucherType::Receipt->value)
            ->where('receipt_voucher_type', 'purchase_return')
            ->where('supplier_id', $supplierId)
            ->where('status', VoucherStatus::Posted->value)
            ->sum('amount');

        return round(max(0, $returns - $receipts), 2);
    }

    private static function formatMoney(float $amount): string
    {
        return app_money($amount);
    }
}
