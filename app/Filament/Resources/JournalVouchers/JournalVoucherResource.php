<?php

declare(strict_types=1);

namespace App\Filament\Resources\JournalVouchers;

use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\JournalVouchers\Pages\CreateJournalVoucher;
use App\Filament\Resources\JournalVouchers\Pages\ListJournalVouchers;
use App\Filament\Resources\JournalVouchers\Pages\ViewJournalVoucher;
use App\Models\Customer;
use App\Models\JournalVoucher;
use App\Models\JournalVoucherAllocation;
use App\Models\Ledger;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\Supplier;
use App\Services\Reports\CustomerLedgerReportService;
use App\Support\CurrentCompany;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
use Illuminate\Support\HtmlString;
use UnitEnum;

class JournalVoucherResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = JournalVoucher::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Voucher';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Journal Voucher';

    protected static ?string $modelLabel = 'Journal Voucher';

    protected static ?string $pluralModelLabel = 'Journal Vouchers';

    public const FORM_TYPES = [
        'credit_note' => 'Credit Note',
        'customer_credit_allocation' => 'Customer Credit Allocation',
        'purchase_return' => 'Purchase Return',
        'sales_invoice_adjustment' => 'Sales Invoice Adjustment',
        'purchase_invoice_adjustment' => 'Purchase Invoice Adjustment',
        'customer_adjustment' => 'Customer Adjustment',
        'supplier_adjustment' => 'Supplier Adjustment',
        'opening_balance' => 'Opening Balance',
        'write_off' => 'Write-off',
        'general_adjustment' => 'General Adjustment',
        'manual' => 'Manual Journal',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Journal Voucher')->schema([
                self::companySelect(),
                Hidden::make('created_by')->default(fn (): ?int => auth()->id()),
                Grid::make(['default' => 1, 'md' => 2, 'xl' => 4])->schema([
                    TextInput::make('voucher_no')
                        ->label('JV No.')
                        ->default(fn (): string => self::nextNumber())
                        ->disabled()->dehydrated(false),
                    DatePicker::make('voucher_date')->label('Entry Date')->default(now())->required(),
                    Select::make('form_type')
                        ->label('Form Type')
                        ->options(self::FORM_TYPES)
                        ->default('credit_note')->required()->live()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('sales_return_id', null);
                            $set('purchase_return_id', null);
                            $set('sales_invoice_id', null);
                            $set('purchase_invoice_id', null);
                            $set('customer_id', null);
                            $set('supplier_id', null);
                            $set('allocations', []);
                            $set('journal_lines', []);
                        }),
                    TextInput::make('reference')->maxLength(255),
                ])->columnSpanFull(),
                Select::make('sales_return_id')
                    ->label('Credit Note No.')
                    ->options(fn (): array => self::creditNoteOptions())
                    ->searchable()->preload()->live()->required(fn (Get $get): bool => $get('form_type') === 'credit_note')
                    ->visible(fn (Get $get): bool => $get('form_type') === 'credit_note')
                    ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                        $set('allocations', []);
                        $return = SalesReturn::query()->with('customer')->find((int) $state);
                        if ($return) {
                            $set('voucher_date', $return->return_date?->toDateString());
                            $set('narration', 'Credit Note '.$return->return_no.' allocated against sales invoice');
                        }
                    }),
                Select::make('purchase_return_id')
                    ->label('Purchase Return No.')
                    ->options(fn (): array => self::purchaseReturnOptions())
                    ->searchable()->preload()->live()->required(fn (Get $get): bool => $get('form_type') === 'purchase_return')
                    ->visible(fn (Get $get): bool => $get('form_type') === 'purchase_return')
                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                        $set('allocations', []);
                        $return = PurchaseReturn::query()->find((int) $state);
                        if ($return) {
                            $set('voucher_date', $return->return_date?->toDateString());
                            $set('narration', 'Purchase Return '.$return->return_no.' allocated against purchase invoice');
                        }
                    }),
                Select::make('sales_invoice_id')
                    ->label('Sales Invoice')
                    ->options(fn (): array => self::salesInvoiceAdjustmentOptions())
                    ->searchable()->preload()->live()->required(fn (Get $get): bool => $get('form_type') === 'sales_invoice_adjustment')
                    ->visible(fn (Get $get): bool => $get('form_type') === 'sales_invoice_adjustment')
                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                        $invoice = self::salesInvoiceForAdjustment((int) $state);

                        if (! $invoice) {
                            $set('reference', null);
                            $set('narration', null);
                            $set('journal_lines', []);

                            return;
                        }

                        $customer = $invoice->customer?->name ?? 'Customer';
                        $particulars = 'Adjustment against Sales Invoice '.$invoice->invoice_no.' - '.$customer;

                        $set('reference', $invoice->invoice_no);
                        $set('narration', $particulars);
                        $set('journal_lines', [
                            ['ledger_id' => null, 'particulars' => $particulars, 'debit' => 0, 'credit' => 0],
                            ['ledger_id' => null, 'particulars' => $particulars, 'debit' => 0, 'credit' => 0],
                        ]);
                    }),
                Select::make('purchase_invoice_id')
                    ->label('Purchase Invoice')
                    ->options(fn (): array => self::purchaseInvoiceAdjustmentOptions())
                    ->searchable()->preload()->live()->required(fn (Get $get): bool => $get('form_type') === 'purchase_invoice_adjustment')
                    ->visible(fn (Get $get): bool => $get('form_type') === 'purchase_invoice_adjustment')
                    ->afterStateUpdated(fn (Set $set, mixed $state): null => self::fillPurchaseInvoiceAdjustment($set, (int) $state)),
                Select::make('customer_id')
                    ->label('Customer')->relationship('customer', 'name')->searchable()->preload()->live()
                    ->required(fn (Get $get): bool => in_array($get('form_type'), ['customer_adjustment', 'customer_credit_allocation'], true))
                    ->visible(fn (Get $get): bool => in_array($get('form_type'), ['customer_adjustment', 'customer_credit_allocation'], true))
                    ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                        if ($get('form_type') === 'customer_credit_allocation') {
                            $customer = self::partyForAdjustment('customer', (int) $state);
                            $set('reference', $customer?->customer_code);
                            $set('narration', $customer ? 'Automatic customer credit allocation for '.$customer->name : null);
                            $set('journal_lines', []);

                            return;
                        }

                        self::fillPartyAdjustment($set, 'customer', (int) $state);
                    }),
                Select::make('supplier_id')
                    ->label('Supplier')->relationship('supplier', 'name')->searchable()->preload()->live()
                    ->required(fn (Get $get): bool => $get('form_type') === 'supplier_adjustment')
                    ->visible(fn (Get $get): bool => $get('form_type') === 'supplier_adjustment')
                    ->afterStateUpdated(fn (Set $set, mixed $state): null => self::fillPartyAdjustment($set, 'supplier', (int) $state)),
                Grid::make(['default' => 1, 'md' => 4])->schema([
                    Placeholder::make('customer_display')->label('Customer')->content(fn (Get $get): string => self::returnValue($get, 'customer')),
                    Placeholder::make('credit_note_date')->label('Credit Note Date')->content(fn (Get $get): string => self::returnValue($get, 'date')),
                    Placeholder::make('credit_note_total')->label('Total Amount')->content(fn (Get $get): string => self::returnValue($get, 'total')),
                    Placeholder::make('credit_note_available')->label('Available')->content(fn (Get $get): string => self::returnValue($get, 'available')),
                ])->visible(fn (Get $get): bool => $get('form_type') === 'credit_note' && filled($get('sales_return_id')))->columnSpanFull(),
                Grid::make(['default' => 1, 'md' => 4])->schema([
                    Placeholder::make('invoice_customer_display')->label('Customer')->content(fn (Get $get): string => self::salesInvoiceValue($get, 'customer')),
                    Placeholder::make('invoice_date_display')->label('Invoice Date')->content(fn (Get $get): string => self::salesInvoiceValue($get, 'date')),
                    Placeholder::make('invoice_total_display')->label('Invoice Total')->content(fn (Get $get): string => self::salesInvoiceValue($get, 'total')),
                    Placeholder::make('invoice_outstanding_display')->label('Outstanding')->content(fn (Get $get): string => self::salesInvoiceValue($get, 'outstanding')),
                ])->visible(fn (Get $get): bool => $get('form_type') === 'sales_invoice_adjustment' && filled($get('sales_invoice_id')))->columnSpanFull(),
                Grid::make(['default' => 1, 'md' => 4])->schema([
                    Placeholder::make('purchase_invoice_supplier_display')->label('Supplier')->content(fn (Get $get): string => self::purchaseInvoiceValue($get, 'supplier')),
                    Placeholder::make('purchase_invoice_date_display')->label('Invoice Date')->content(fn (Get $get): string => self::purchaseInvoiceValue($get, 'date')),
                    Placeholder::make('purchase_invoice_total_display')->label('Invoice Total')->content(fn (Get $get): string => self::purchaseInvoiceValue($get, 'total')),
                    Placeholder::make('purchase_invoice_outstanding_display')->label('Outstanding')->content(fn (Get $get): string => self::purchaseInvoiceValue($get, 'outstanding')),
                ])->visible(fn (Get $get): bool => $get('form_type') === 'purchase_invoice_adjustment' && filled($get('purchase_invoice_id')))->columnSpanFull(),
                Grid::make(['default' => 1, 'md' => 4])->schema([
                    Placeholder::make('party_name_display')->label('Name')->content(fn (Get $get): string => self::partyValue($get, 'name')),
                    Placeholder::make('party_code_display')->label('Code')->content(fn (Get $get): string => self::partyValue($get, 'code')),
                    Placeholder::make('party_contact_display')->label('Contact')->content(fn (Get $get): string => self::partyValue($get, 'contact')),
                    Placeholder::make('party_balance_display')->label('Current Balance')->content(fn (Get $get): string => self::partyValue($get, 'balance')),
                ])->visible(fn (Get $get): bool => in_array($get('form_type'), ['customer_adjustment', 'supplier_adjustment'], true) && (filled($get('customer_id')) || filled($get('supplier_id'))))->columnSpanFull(),
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    Placeholder::make('credit_invoice_outstanding')->label('Invoice Outstanding')->content(fn (Get $get): string => self::customerCreditValue($get, 'outstanding')),
                    Placeholder::make('credit_available')->label('Unallocated Credit')->content(fn (Get $get): string => self::customerCreditValue($get, 'credit')),
                    Placeholder::make('credit_method')->label('Allocation Method')->content('Oldest due invoice first (FIFO)'),
                ])->visible(fn (Get $get): bool => $get('form_type') === 'customer_credit_allocation' && filled($get('customer_id')))->columnSpanFull(),
                Placeholder::make('credit_allocation_notice')
                    ->label('Posting Effect')
                    ->content('Allocates existing receipt and credit-note funds only. No additional accounting journal is created. Any excess remains as customer credit.')
                    ->visible(fn (Get $get): bool => $get('form_type') === 'customer_credit_allocation')
                    ->columnSpanFull(),
                Placeholder::make('accounting_preview')
                    ->label('Accounting Entries (read-only)')
                    ->content(fn (Get $get, ?JournalVoucher $record): HtmlString => self::accountingPreview($get, $record))
                    ->visible(fn (Get $get): bool => in_array($get('form_type'), ['credit_note', 'purchase_return', 'sales_invoice_adjustment', 'purchase_invoice_adjustment'], true))
                    ->columnSpanFull(),
                Repeater::make('allocations')
                    ->relationship()
                    ->label('Sales Invoice Allocations')
                    ->table([
                        TableColumn::make('Sales Invoice')->width('55%'),
                        TableColumn::make('Allocation Amount')->alignment(Alignment::Center),
                    ])
                    ->schema([
                        Select::make('sales_invoice_id')
                            ->label('Sales Invoice')->hiddenLabel()->searchable()->required()
                            ->options(fn (Get $get): array => self::invoiceOptions((int) ($get('../../sales_return_id') ?? 0))),
                        TextInput::make('amount')->hiddenLabel()->numeric()->step('0.01')->minValue(0.01)->required(),
                    ])
                    ->defaultItems(0)->reorderable(false)->columnSpanFull()
                    ->visible(fn (Get $get): bool => $get('form_type') === 'credit_note' && filled($get('sales_return_id'))),
                Repeater::make('purchase_allocations')
                    ->relationship('allocations')
                    ->label('Purchase Invoice Allocations')
                    ->table([
                        TableColumn::make('Purchase Invoice')->width('55%'),
                        TableColumn::make('Allocation Amount')->alignment(Alignment::Center),
                    ])
                    ->schema([
                        Select::make('purchase_invoice_id')->label('Purchase Invoice')->hiddenLabel()->searchable()->required()
                            ->options(fn (Get $get): array => self::purchaseInvoiceOptions((int) ($get('../../purchase_return_id') ?? 0))),
                        TextInput::make('amount')->hiddenLabel()->numeric()->step('0.01')->minValue(0.01)->required(),
                    ])
                    ->defaultItems(0)->reorderable(false)->columnSpanFull()
                    ->visible(fn (Get $get): bool => $get('form_type') === 'purchase_return' && filled($get('purchase_return_id'))),
                Repeater::make('journal_lines')
                    ->label('Journal Entries')
                    ->table([
                        TableColumn::make('Account Head')->width('35%'),
                        TableColumn::make('Particulars')->width('35%'),
                        TableColumn::make('Debit')->alignment(Alignment::Center),
                        TableColumn::make('Credit')->alignment(Alignment::Center),
                    ])
                    ->schema([
                        Select::make('ledger_id')->label('Account Head')->hiddenLabel()->options(fn (): array => self::ledgerOptions())->searchable()->preload()->required(),
                        TextInput::make('particulars')->hiddenLabel()->maxLength(255),
                        TextInput::make('debit')->hiddenLabel()->numeric()->step('0.01')->default(0)->required(),
                        TextInput::make('credit')->hiddenLabel()->numeric()->step('0.01')->default(0)->required(),
                    ])
                    ->live()
                    ->minItems(2)->defaultItems(2)->reorderable(false)->columnSpanFull()
                    ->visible(fn (Get $get): bool => self::usesManualLines((string) $get('form_type'))),
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    Placeholder::make('debit_total_display')->label('Total Debit')->content(fn (Get $get): string => app_money(self::lineTotal($get, 'debit'))),
                    Placeholder::make('credit_total_display')->label('Total Credit')->content(fn (Get $get): string => app_money(self::lineTotal($get, 'credit'))),
                    Placeholder::make('difference_display')->label('Difference')->content(fn (Get $get): string => app_money(abs(self::lineTotal($get, 'debit') - self::lineTotal($get, 'credit')))),
                ])->visible(fn (Get $get): bool => self::usesManualLines((string) $get('form_type')))->columnSpanFull(),
                Textarea::make('narration')->required()->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('voucher_no')->label('JV No.')->searchable()->sortable(),
            TextColumn::make('voucher_date')->label('JV Date')->date()->sortable(),
            TextColumn::make('form_type')->label('Form Type')->formatStateUsing(fn (string $state): string => self::FORM_TYPES[$state] ?? str($state)->headline()->toString())->badge(),
            TextColumn::make('source_document')->label('Source Document')->state(fn (JournalVoucher $record): string => self::sourceDocument($record))->placeholder('Manual'),
            TextColumn::make('party')->label('Customer / Supplier')->state(fn (JournalVoucher $record): string => self::sourceParty($record))->placeholder('—'),
            TextColumn::make('journalEntry.debit_total')->label('Debit')->formatStateUsing(fn (mixed $state): string => app_money($state ?? 0)),
            TextColumn::make('journalEntry.credit_total')->label('Credit')->formatStateUsing(fn (mixed $state): string => app_money($state ?? 0)),
            TextColumn::make('createdBy.name')->label('Created By'),
        ])->filters([SelectFilter::make('form_type')->options(self::FORM_TYPES)])
            ->defaultSort('voucher_date', 'desc')->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJournalVouchers::route('/'),
            'create' => CreateJournalVoucher::route('/create'),
            'view' => ViewJournalVoucher::route('/{record}'),
        ];
    }

    private static function nextNumber(): string
    {
        $companyId = app(CurrentCompany::class)->id();

        return $companyId ? JournalVoucher::nextVoucherNo($companyId) : '';
    }

    private static function creditNoteOptions(): array
    {
        return SalesReturn::query()->whereNotNull('journal_id')->whereDoesntHave('journalVoucher')
            ->with('customer')->orderByDesc('return_date')->get()
            ->mapWithKeys(fn (SalesReturn $return): array => [$return->id => $return->return_no.' — '.($return->customer?->name ?? 'Unknown').' — '.app_money($return->total)])
            ->all();
    }

    private static function purchaseReturnOptions(): array
    {
        return PurchaseReturn::query()->whereNotNull('journal_id')->whereDoesntHave('journalVoucher')
            ->with('supplier')->orderByDesc('return_date')->get()
            ->mapWithKeys(fn (PurchaseReturn $return): array => [$return->id => $return->return_no.' — '.($return->supplier?->name ?? 'Unknown').' — '.app_money($return->total)])
            ->all();
    }

    private static function invoiceOptions(int $returnId): array
    {
        $return = SalesReturn::query()->find($returnId);
        if (! $return) {
            return [];
        }

        return SalesInvoice::query()->where('customer_id', $return->customer_id)
            ->whereNotIn('status', ['draft', 'cancelled'])->orderByDesc('invoice_date')->get()
            ->filter(fn (SalesInvoice $invoice): bool => self::invoiceOutstanding($invoice) > 0)
            ->mapWithKeys(fn (SalesInvoice $invoice): array => [$invoice->id => $invoice->invoice_no.' — '.app_money(self::invoiceOutstanding($invoice)).' outstanding'])
            ->all();
    }

    private static function invoiceOutstanding(SalesInvoice $invoice): float
    {
        return round(max(0, (float) $invoice->total - (float) $invoice->allocations()->sum('amount') - (float) $invoice->journalVoucherAllocations()->sum('amount')), 2);
    }

    private static function salesInvoiceAdjustmentOptions(): array
    {
        $companyId = app(CurrentCompany::class)->id();

        if (! $companyId) {
            return [];
        }

        return SalesInvoice::query()
            ->where('company_id', $companyId)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->with('customer')
            ->orderByDesc('invoice_date')
            ->get()
            ->mapWithKeys(fn (SalesInvoice $invoice): array => [
                $invoice->id => $invoice->invoice_no.' — '.($invoice->customer?->name ?? 'Unknown').' — '.app_money($invoice->total),
            ])
            ->all();
    }

    private static function salesInvoiceForAdjustment(int $invoiceId): ?SalesInvoice
    {
        $companyId = app(CurrentCompany::class)->id();

        if (! $companyId || $invoiceId <= 0) {
            return null;
        }

        return SalesInvoice::query()
            ->where('company_id', $companyId)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->with(['customer', 'journalEntry.journalLines.ledger'])
            ->find($invoiceId);
    }

    private static function salesInvoiceValue(Get $get, string $field): string
    {
        $invoice = self::salesInvoiceForAdjustment((int) ($get('sales_invoice_id') ?? 0));

        if (! $invoice) {
            return '—';
        }

        return match ($field) {
            'customer' => $invoice->customer?->name ?? '—',
            'date' => $invoice->invoice_date?->format('d M Y') ?? '—',
            'total' => app_money($invoice->total),
            'outstanding' => app_money(self::invoiceOutstanding($invoice)),
            default => '—',
        };
    }

    private static function purchaseInvoiceAdjustmentOptions(): array
    {
        $companyId = app(CurrentCompany::class)->id();

        if (! $companyId) {
            return [];
        }

        return PurchaseInvoice::query()
            ->where('company_id', $companyId)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->with('supplier')
            ->orderByDesc('invoice_date')
            ->get()
            ->mapWithKeys(fn (PurchaseInvoice $invoice): array => [
                $invoice->id => $invoice->displayReference().' — '.($invoice->supplier?->name ?? 'Unknown').' — '.app_money($invoice->total),
            ])
            ->all();
    }

    private static function purchaseInvoiceForAdjustment(int $invoiceId): ?PurchaseInvoice
    {
        $companyId = app(CurrentCompany::class)->id();

        if (! $companyId || $invoiceId <= 0) {
            return null;
        }

        return PurchaseInvoice::query()
            ->where('company_id', $companyId)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->with(['supplier', 'journalEntry.journalLines.ledger'])
            ->find($invoiceId);
    }

    private static function fillPurchaseInvoiceAdjustment(Set $set, int $invoiceId): null
    {
        $invoice = self::purchaseInvoiceForAdjustment($invoiceId);

        if (! $invoice) {
            self::clearAdjustmentDefaults($set);

            return null;
        }

        self::setAdjustmentDefaults($set, $invoice->displayReference(), 'Purchase Invoice', $invoice->supplier?->name ?? 'Supplier');

        return null;
    }

    private static function purchaseInvoiceValue(Get $get, string $field): string
    {
        $invoice = self::purchaseInvoiceForAdjustment((int) ($get('purchase_invoice_id') ?? 0));

        if (! $invoice) {
            return '—';
        }

        return match ($field) {
            'supplier' => $invoice->supplier?->name ?? '—',
            'date' => $invoice->invoice_date?->format('d M Y') ?? '—',
            'total' => app_money($invoice->total),
            'outstanding' => app_money(self::purchaseInvoiceOutstanding($invoice)),
            default => '—',
        };
    }

    private static function fillPartyAdjustment(Set $set, string $type, int $partyId): null
    {
        $party = self::partyForAdjustment($type, $partyId);

        if (! $party) {
            self::clearAdjustmentDefaults($set);

            return null;
        }

        $code = $type === 'customer' ? $party->customer_code : $party->supplier_code;
        self::setAdjustmentDefaults($set, (string) $code, ucfirst($type), (string) $party->name);

        return null;
    }

    private static function partyForAdjustment(string $type, int $partyId): Customer|Supplier|null
    {
        $companyId = app(CurrentCompany::class)->id();

        if (! $companyId || $partyId <= 0) {
            return null;
        }

        $model = $type === 'customer' ? Customer::query() : Supplier::query();

        return $model->where('company_id', $companyId)->find($partyId);
    }

    private static function partyValue(Get $get, string $field): string
    {
        $type = $get('form_type') === 'supplier_adjustment' ? 'supplier' : 'customer';
        $partyId = (int) ($type === 'supplier' ? $get('supplier_id') : $get('customer_id'));
        $party = self::partyForAdjustment($type, $partyId);

        if (! $party) {
            return '—';
        }

        return match ($field) {
            'name' => $party->name ?: '—',
            'code' => ($type === 'customer' ? $party->customer_code : $party->supplier_code) ?: '—',
            'contact' => $party->email ?: ($party->phone ?: '—'),
            'balance' => app_money(self::partyBalance($party)),
            default => '—',
        };
    }

    private static function partyBalance(Customer|Supplier $party): float
    {
        if ($party instanceof Customer) {
            $opening = (string) ($party->balance_type?->value ?? $party->balance_type) === 'Cr' ? -(float) $party->opening_balance : (float) $party->opening_balance;
            $documents = (float) SalesInvoice::query()->where('customer_id', $party->id)->whereNotIn('status', ['draft', 'cancelled'])->sum('total');
            $cash = (float) $party->vouchers()->where('voucher_type', VoucherType::Receipt->value)->where('status', VoucherStatus::Posted->value)->sum('amount');
            $credits = (float) JournalVoucherAllocation::query()
                ->whereHas('journalVoucher.salesReturn', fn ($query) => $query->where('customer_id', $party->id))
                ->sum('amount');

            return round($opening + $documents - $cash - $credits, 2);
        }

        $opening = (string) ($party->balance_type?->value ?? $party->balance_type) === 'Dr' ? -(float) $party->opening_balance : (float) $party->opening_balance;
        $documents = (float) PurchaseInvoice::query()->where('supplier_id', $party->id)->whereNotIn('status', ['draft', 'cancelled'])->sum('total');
        $cash = (float) $party->vouchers()->where('voucher_type', VoucherType::Payment->value)->where('status', VoucherStatus::Posted->value)->sum('amount');
        $returns = (float) JournalVoucherAllocation::query()
            ->whereHas('journalVoucher.purchaseReturn', fn ($query) => $query->where('supplier_id', $party->id))
            ->sum('amount');

        return round($opening + $documents - $cash - $returns, 2);
    }

    private static function customerCreditValue(Get $get, string $field): string
    {
        $customer = self::partyForAdjustment('customer', (int) ($get('customer_id') ?? 0));

        if (! $customer) {
            return '—';
        }

        $summary = app(CustomerLedgerReportService::class)->summary($customer);

        return $field === 'credit'
            ? $summary['unallocated_credit_formatted']
            : $summary['invoice_outstanding_formatted'];
    }

    private static function setAdjustmentDefaults(Set $set, string $reference, string $sourceLabel, string $partyName): void
    {
        $particulars = 'Adjustment against '.$sourceLabel.' '.$reference.' - '.$partyName;
        $set('reference', $reference);
        $set('narration', $particulars);
        $set('journal_lines', [
            ['ledger_id' => null, 'particulars' => $particulars, 'debit' => 0, 'credit' => 0],
            ['ledger_id' => null, 'particulars' => $particulars, 'debit' => 0, 'credit' => 0],
        ]);
    }

    private static function clearAdjustmentDefaults(Set $set): void
    {
        $set('reference', null);
        $set('narration', null);
        $set('journal_lines', []);
    }

    private static function purchaseInvoiceOptions(int $returnId): array
    {
        $return = PurchaseReturn::query()->find($returnId);
        if (! $return) {
            return [];
        }

        return PurchaseInvoice::query()->where('supplier_id', $return->supplier_id)
            ->whereNotIn('status', ['draft', 'cancelled'])->orderByDesc('invoice_date')->get()
            ->filter(fn (PurchaseInvoice $invoice): bool => self::purchaseInvoiceOutstanding($invoice) > 0)
            ->mapWithKeys(fn (PurchaseInvoice $invoice): array => [$invoice->id => $invoice->displayReference().' — '.app_money(self::purchaseInvoiceOutstanding($invoice)).' outstanding'])
            ->all();
    }

    private static function purchaseInvoiceOutstanding(PurchaseInvoice $invoice): float
    {
        return round(max(0, (float) $invoice->total - (float) $invoice->allocations()->sum('amount') - (float) $invoice->journalVoucherAllocations()->sum('amount')), 2);
    }

    private static function ledgerOptions(): array
    {
        return Ledger::query()->orderBy('nominal_code')->get()
            ->mapWithKeys(fn (Ledger $ledger): array => [$ledger->id => $ledger->nominal_code.' — '.$ledger->name])->all();
    }

    private static function returnValue(Get $get, string $field): string
    {
        $return = SalesReturn::query()->with('customer')->find((int) ($get('sales_return_id') ?? 0));
        if (! $return) {
            return '—';
        }

        return match ($field) {
            'customer' => $return->customer?->name ?? '—',
            'date' => $return->return_date?->format('d M Y') ?? '—',
            'total' => app_money($return->total),
            'available' => app_money(max(0, (float) $return->total - (float) ($return->journalVoucher?->allocations()->sum('amount') ?? 0))),
            default => '—',
        };
    }

    private static function lineTotal(Get $get, string $side): float
    {
        return round((float) collect($get('journal_lines') ?? [])->sum(fn (array $line): float => (float) ($line[$side] ?? 0)), 2);
    }

    private static function usesManualLines(string $formType): bool
    {
        return ! in_array($formType, ['credit_note', 'purchase_return', 'customer_credit_allocation'], true);
    }

    private static function sourceDocument(JournalVoucher $voucher): string
    {
        return match ($voucher->form_type) {
            'credit_note' => $voucher->salesReturn?->return_no ?? '—',
            'purchase_return' => $voucher->purchaseReturn?->return_no ?? '—',
            'sales_invoice_adjustment' => $voucher->salesInvoice?->invoice_no ?? '—',
            'purchase_invoice_adjustment' => $voucher->purchaseInvoice?->displayReference() ?? '—',
            'customer_adjustment' => $voucher->customer?->name ?? '—',
            'customer_credit_allocation' => $voucher->customer?->customer_code ?? '—',
            'supplier_adjustment' => $voucher->supplier?->name ?? '—',
            default => 'Manual',
        };
    }

    private static function sourceParty(JournalVoucher $voucher): string
    {
        return match ($voucher->form_type) {
            'credit_note' => $voucher->salesReturn?->customer?->name ?? '—',
            'purchase_return' => $voucher->purchaseReturn?->supplier?->name ?? '—',
            'sales_invoice_adjustment' => $voucher->salesInvoice?->customer?->name ?? '—',
            'purchase_invoice_adjustment' => $voucher->purchaseInvoice?->supplier?->name ?? '—',
            'customer_adjustment' => $voucher->customer?->name ?? '—',
            'customer_credit_allocation' => $voucher->customer?->name ?? '—',
            'supplier_adjustment' => $voucher->supplier?->name ?? '—',
            default => '—',
        };
    }

    private static function accountingPreview(Get $get, ?JournalVoucher $record): HtmlString
    {
        $formType = (string) ($get('form_type') ?: $record?->form_type);
        $source = match ($formType) {
            'purchase_return' => PurchaseReturn::query()->with('journalEntry.journalLines.ledger')->find((int) ($get('purchase_return_id') ?: $record?->purchase_return_id)),
            'sales_invoice_adjustment' => self::salesInvoiceForAdjustment((int) ($get('sales_invoice_id') ?: $record?->sales_invoice_id)),
            'purchase_invoice_adjustment' => self::purchaseInvoiceForAdjustment((int) ($get('purchase_invoice_id') ?: $record?->purchase_invoice_id)),
            default => SalesReturn::query()->with('journalEntry.journalLines.ledger')->find((int) ($get('sales_return_id') ?: $record?->sales_return_id)),
        };

        if (! $source?->journalEntry) {
            return new HtmlString('<span class="text-sm text-gray-500">Select a posted source document to preview its balanced entries.</span>');
        }

        $rows = $source->journalEntry->journalLines->map(fn ($line): string => '<tr class="border-b"><td class="p-2">'.e($line->ledger?->nominal_code).'</td><td class="p-2">'.e($line->ledger?->name).'</td><td class="p-2 text-right">'.e(app_money($line->debit)).'</td><td class="p-2 text-right">'.e(app_money($line->credit)).'</td></tr>')->implode('');

        return new HtmlString('<div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="border-b"><th class="p-2 text-left">Account</th><th class="p-2 text-left">Account Name</th><th class="p-2 text-right">Debit</th><th class="p-2 text-right">Credit</th></tr></thead><tbody>'.$rows.'</tbody><tfoot><tr class="font-semibold"><td class="p-2" colspan="2">Total</td><td class="p-2 text-right">'.e(app_money($source->journalEntry->debit_total)).'</td><td class="p-2 text-right">'.e(app_money($source->journalEntry->credit_total)).'</td></tr></tfoot></table></div>');
    }
}
