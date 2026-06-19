<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesInvoices;

use App\Enums\InvoiceStatus;
use App\Enums\SalesReturnStatus;
use App\Enums\Status;
use App\Enums\VoucherStatus;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\SalesInvoices\Pages\CreateSalesInvoice;
use App\Filament\Resources\SalesInvoices\Pages\EditSalesInvoice;
use App\Filament\Resources\SalesInvoices\Pages\ListSalesInvoices;
use App\Filament\Resources\SalesReturns\SalesReturnResource;
use App\Models\BankTransaction;
use App\Models\Customer;
use App\Models\ProductItem;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\TaxRate;
use App\Models\VoucherAllocation;
use App\Services\Accounting\SalesPostingService;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use UnitEnum;

class SalesInvoiceResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = SalesInvoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Sale Entry';

    protected static ?string $modelLabel = 'Sales Invoice';

    protected static ?string $pluralModelLabel = 'Sales Invoices';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->extraAttributes(['class' => 'sales-invoice-form'])
                ->schema([
                    self::companySelect(),
                    Hidden::make('subtotal')->default(0),
                    Hidden::make('vat_total')->default(0),
                    Hidden::make('total')->default(0),
                    Grid::make([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 6,
                    ])->schema([
                        Grid::make(1)->schema([
                            Select::make('customer_id')
                                ->label('Billed To')
                                ->placeholder('Search for a client')
                                ->relationship('customer', 'name')
                                ->searchable()
                                ->preload()
                                ->live()
                                ->required()
                                ->createOptionForm([
                                    Hidden::make('company_id')
                                        ->default(fn (): ?int => auth()->user()?->company_id),
                                    TextInput::make('company_name')
                                        ->label('Client Name')
                                        ->required()
                                        ->maxLength(255),
                                    TextInput::make('contact_person')
                                        ->maxLength(255),
                                    TextInput::make('email')
                                        ->email()
                                        ->maxLength(255),
                                    TextInput::make('mobile_no')
                                        ->label('Mobile Number')
                                        ->maxLength(255),
                                ])
                                ->createOptionUsing(fn (array $data): int => Customer::create([
                                    ...$data,
                                    'status' => Status::Active,
                                ])->getKey()),
                            Placeholder::make('customer_address_display')
                                ->label('Customer Address')
                                ->content(fn (Get $get): HtmlString => self::customerAddressDisplay((int) ($get('customer_id') ?? 0)))
                                ->extraAttributes(['class' => 'sales-invoice-form__customer-address']),
                        ])->columnSpan([
                            'default' => 1,
                            'xl' => 2,
                        ]),
                        Grid::make(1)->schema([
                            DatePicker::make('invoice_date')
                                ->label('Date of Issue')
                                ->required()
                                ->default(now())
                                ->live()
                                ->afterStateUpdated(fn (Get $get, Set $set): null => self::syncInvoiceNumber($get, $set)),
                            DatePicker::make('due_date')
                                ->label('Due Date'),
                        ]),
                        Grid::make(1)->schema([
                            TextInput::make('invoice_no')
                                ->label('Invoice Number')
                                ->required()
                                ->default(fn (Get $get): string => self::nextInvoiceNumber(
                                    auth()->user()?->company_id,
                                    $get('invoice_date') ?: now(),
                                ))
                                ->readOnly()
                                ->maxLength(255),
                            TextInput::make('payment_note')
                                ->label('Reference')
                                ->placeholder('Enter value (e.g. PO #)')
                                ->maxLength(255),
                        ]),
                        Select::make('status')
                            ->options(InvoiceStatus::class)
                            ->default(InvoiceStatus::Draft)
                            ->required(),
                        Grid::make(1)->schema([
                            Placeholder::make('amount_due_display')
                                ->label(fn (): string => 'Amount Due ('.self::currencySymbol().')')
                                ->content(fn (Get $get, ?SalesInvoice $record): string => self::formatMoney(self::displayAmountDue($get, $record)))
                                ->extraAttributes(['class' => 'sales-invoice-form__amount-due']),
                            Placeholder::make('customer_balance_display')
                                ->label('Pending / Opening Balance')
                                ->content(fn (Get $get): string => self::customerBalanceDisplay((int) ($get('customer_id') ?? 0)))
                                ->extraAttributes(['class' => 'sales-invoice-form__customer-balance']),
                        ]),
                    ])->columnSpanFull(),
                    Repeater::make('items')
                        ->label('')
                        ->relationship()
                        ->table([
                            TableColumn::make('Description')->alignment(Alignment::Center)->width('46%'),
                            TableColumn::make('Rate')->alignment(Alignment::Center)->width('14%'),
                            TableColumn::make('Qty')->alignment(Alignment::Center)->width('10%'),
                            TableColumn::make('Tax %')->alignment(Alignment::Center)->width('10%'),
                            TableColumn::make('Line Total')->alignment(Alignment::Center)->width('14%'),
                        ])
                        ->schema([
                            Grid::make(1)->schema([
                                Select::make('product_item_id')
                                    ->label('Product')
                                    ->hiddenLabel()
                                    ->relationship('productItem', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, ?int $state): void {
                                        if (! $state) {
                                            return;
                                        }

                                        $product = ProductItem::query()->find($state);

                                        if (! $product) {
                                            return;
                                        }

                                        $set('description', $product->description ?: $product->name);
                                        $set('rate', $product->sale_price ?? 0, shouldCallUpdatedHooks: true);
                                        $set('tax_rate_id', $product->tax_rate_id ?: TaxRate::idForRate($product->vat_rate) ?: TaxRate::defaultId(), shouldCallUpdatedHooks: true);
                                        $set('vat_rate', $product->vat_rate ?? 20, shouldCallUpdatedHooks: true);
                                    }),
                                Textarea::make('description')
                                    ->hiddenLabel()
                                    ->placeholder('Product description')
                                    ->rows(1)
                                    ->maxLength(255),
                            ])->extraAttributes(['class' => 'sales-invoice-form__description-cell']),
                            TextInput::make('rate')
                                ->hiddenLabel()
                                ->numeric()
                                ->required()
                                ->default(0)
                                ->step('0.01')
                                ->prefix(fn (): string => self::currencySymbol())
                                ->extraAttributes(['class' => 'sales-invoice-form__centered-field'])
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Get $get, Set $set): null => self::syncLineAndInvoiceTotals($get, $set)),
                            TextInput::make('qty')
                                ->hiddenLabel()
                                ->numeric()
                                ->required()
                                ->default(1)
                                ->step('0.001')
                                ->extraAttributes(['class' => 'sales-invoice-form__centered-field'])
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Get $get, Set $set): null => self::syncLineAndInvoiceTotals($get, $set)),
                            Select::make('tax_rate_id')
                                ->hiddenLabel()
                                ->options(fn (): array => TaxRate::options())
                                ->default(fn (): int => TaxRate::defaultId())
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set, ?int $state): null {
                                    $set('vat_rate', TaxRate::rateFor($state));

                                    return self::syncLineAndInvoiceTotals($get, $set);
                                })
                                ->extraAttributes(['class' => 'sales-invoice-form__centered-field']),
                            Hidden::make('vat_rate')
                                ->default(20),
                            Placeholder::make('line_total_display')
                                ->hiddenLabel()
                                ->content(fn (Get $get): string => self::formatMoney((float) ($get('line_total') ?? 0)))
                                ->extraAttributes(['class' => 'sales-invoice-form__line-total']),
                            Hidden::make('vat_amount')->default(0),
                            Hidden::make('line_total')->default(0),
                        ])
                        ->addActionLabel('Add a Line')
                        ->addAction(fn (Action $action): Action => $action
                            ->icon(Heroicon::Plus)
                            ->button()
                            ->color('gray')
                            ->extraAttributes(['class' => 'sales-invoice-form__add-line']))
                        ->deleteAction(fn (Action $action): Action => $action
                            ->icon(Heroicon::Trash)
                            ->iconButton()
                            ->color('gray'))
                        ->defaultItems(1)
                        ->minItems(1)
                        ->reorderable()
                        ->compact()
                        ->extraAttributes(['class' => 'sales-invoice-form__lines'])
                        ->columnSpanFull(),
                    Textarea::make('notes')
                        ->label('Notes')
                        ->rows(3)
                        ->placeholder('Add invoice notes')
                        ->columnSpanFull(),
                    Grid::make(1)->schema([
                        Grid::make(1)->schema([
                            Placeholder::make('subtotal_display')
                                ->label('Subtotal')
                                ->inlineLabel()
                                ->content(fn (Get $get): string => self::formatMoney(self::currentSubtotal($get))),
                            TextInput::make('discount')
                                ->label('Discount')
                                ->inlineLabel()
                                ->numeric()
                                ->default(0)
                                ->step('0.01')
                                ->prefix(fn (): string => self::currencySymbol())
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Get $get, Set $set): null => self::syncInvoiceTotals($get, $set)),
                            Placeholder::make('tax_display')
                                ->label('Tax')
                                ->inlineLabel()
                                ->content(fn (Get $get): string => self::formatMoney(self::currentTax($get))),
                            Placeholder::make('total_display')
                                ->label('Total')
                                ->inlineLabel()
                                ->content(fn (Get $get): string => self::formatMoney(self::currentAmountDue($get))),
                            Placeholder::make('amount_paid_display')
                                ->label('Amount Paid')
                                ->inlineLabel()
                                ->content(fn (?SalesInvoice $record): string => self::formatMoney(self::invoicePaidAmount($record))),
                            Placeholder::make('amount_due_summary_display')
                                ->label(fn (): string => 'Amount Due ('.self::currencySymbol().')')
                                ->inlineLabel()
                                ->content(fn (Get $get, ?SalesInvoice $record): string => self::formatMoney(self::displayAmountDue($get, $record)))
                                ->extraAttributes(['class' => 'sales-invoice-form__total-due']),
                        ])->extraAttributes(['class' => 'sales-invoice-form__totals']),
                    ])->extraAttributes(['class' => 'sales-invoice-form__summary-row'])->columnSpanFull(),
                ])->columns(1)->columnSpanFull(),
        ]);
    }

    public static function calculateTotalsFromData(array $data): array
    {
        $subtotal = 0.0;
        $vatTotal = 0.0;

        foreach (($data['items'] ?? []) as $index => $item) {
            $qty = (float) ($item['qty'] ?? 0);
            $rate = (float) ($item['rate'] ?? 0);
            $vatRate = filled($item['tax_rate_id'] ?? null)
                ? TaxRate::rateFor((int) $item['tax_rate_id'])
                : (float) ($item['vat_rate'] ?? 0);
            $lineSubtotal = round($qty * $rate, 2);
            $vatAmount = round($lineSubtotal * ($vatRate / 100), 2);

            $data['items'][$index]['vat_rate'] = $vatRate;
            $data['items'][$index]['vat_amount'] = $vatAmount;
            $data['items'][$index]['line_total'] = $lineSubtotal + $vatAmount;

            $subtotal += $lineSubtotal;
            $vatTotal += $vatAmount;
        }

        $discount = round((float) ($data['discount'] ?? 0), 2);

        $data['subtotal'] = round($subtotal, 2);
        $data['discount'] = $discount;
        $data['vat_total'] = round($vatTotal, 2);
        $data['total'] = round(max(0, $subtotal + $vatTotal - $discount), 2);

        return $data;
    }

    public static function nextInvoiceNumber(?int $companyId, mixed $invoiceDate = null): string
    {
        $date = filled($invoiceDate) ? Carbon::parse($invoiceDate) : now();
        $prefix = 'POS-'.$date->format('Ymd').'-';
        $latestInvoiceNo = SalesInvoice::withoutGlobalScopes()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->where('invoice_no', 'like', $prefix.'%')
            ->orderByDesc('invoice_no')
            ->value('invoice_no');

        $nextNumber = $latestInvoiceNo ? ((int) substr($latestInvoiceNo, -4)) + 1 : 1;

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_no')->searchable()->sortable(),
                TextColumn::make('customer.name')->searchable()->sortable(),
                TextColumn::make('invoice_date')->date()->sortable(),
                TextColumn::make('total')
                    ->formatStateUsing(fn (mixed $state): string => self::formatMoney((float) $state))
                    ->sortable(),
                TextColumn::make('paid_amount')
                    ->label('Paid')
                    ->state(fn (SalesInvoice $record): float => self::invoicePaidAmount($record))
                    ->formatStateUsing(fn (mixed $state): string => self::formatMoney((float) $state)),
                TextColumn::make('amount_due')
                    ->label('Due')
                    ->state(fn (SalesInvoice $record): float => self::invoiceOutstandingAmount($record))
                    ->formatStateUsing(fn (mixed $state): string => self::formatMoney((float) $state)),
                TextColumn::make('status')->badge()->sortable(),
            ])
            ->filters([self::statusFilter(InvoiceStatus::class), self::dateRangeFilter('invoice_date')])
            ->defaultSort('invoice_date', 'desc')
            ->recordActions([
                Action::make('post')
                    ->icon(Heroicon::CheckCircle)
                    ->requiresConfirmation()
                    ->visible(fn (SalesInvoice $record): bool => $record->status === InvoiceStatus::Draft)
                    ->action(function (SalesInvoice $record): void {
                        app(SalesPostingService::class)->post($record);
                        Notification::make()->title('Sales invoice posted')->success()->send();
                    }),
                Action::make('return_sale')
                    ->label('Return Sale')
                    ->icon(Heroicon::ArrowUturnLeft)
                    ->color('warning')
                    ->visible(fn (SalesInvoice $record): bool => $record->status === InvoiceStatus::Paid)
                    ->url(fn (SalesInvoice $record): string => SalesReturnResource::getUrl('create', [
                        'sales_invoice_id' => $record->id,
                    ])),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesInvoices::route('/'),
            'create' => CreateSalesInvoice::route('/create'),
            'edit' => EditSalesInvoice::route('/{record}/edit'),
        ];
    }

    private static function syncLineAndInvoiceTotals(Get $get, Set $set): null
    {
        $qty = (float) ($get('qty') ?? 0);
        $rate = (float) ($get('rate') ?? 0);
        $vatRate = (float) ($get('vat_rate') ?? 0);
        $lineSubtotal = round($qty * $rate, 2);
        $vatAmount = round($lineSubtotal * ($vatRate / 100), 2);

        $set('vat_amount', $vatAmount);
        $set('line_total', $lineSubtotal + $vatAmount);

        self::syncInvoiceTotals($get, $set, '../../');

        return null;
    }

    private static function syncInvoiceTotals(Get $get, Set $set, string $parentPath = ''): null
    {
        $data = self::calculateTotalsFromData([
            'items' => (array) ($get($parentPath.'items') ?? []),
            'discount' => $get($parentPath.'discount') ?? 0,
        ]);

        $set($parentPath.'subtotal', $data['subtotal']);
        $set($parentPath.'vat_total', $data['vat_total']);
        $set($parentPath.'total', $data['total']);

        return null;
    }

    private static function syncInvoiceNumber(Get $get, Set $set): null
    {
        $set('invoice_no', self::nextInvoiceNumber(auth()->user()?->company_id, $get('invoice_date') ?: now()));

        return null;
    }

    private static function customerBalanceDisplay(int $customerId): string
    {
        if ($customerId < 1) {
            return 'Select a customer';
        }

        return self::formatMoney(self::customerBalance($customerId));
    }

    private static function customerAddressDisplay(int $customerId): HtmlString
    {
        if ($customerId < 1) {
            return new HtmlString('<span class="text-gray-500">Select a customer to view address</span>');
        }

        $customer = Customer::query()->find($customerId);

        if (! $customer) {
            return new HtmlString('<span class="text-gray-500">Customer not found</span>');
        }

        $lines = collect([
            $customer->billing_address,
            $customer->address_line1,
            $customer->address_line2,
            collect([$customer->city, $customer->postcode])->filter()->join(', '),
            $customer->country,
        ])->filter()->unique()->map(fn (string $line): string => e($line))->implode('<br>');

        return new HtmlString($lines !== '' ? $lines : '<span class="text-gray-500">No address saved</span>');
    }

    private static function customerBalance(int $customerId): float
    {
        $customer = Customer::query()->find($customerId);

        if (! $customer) {
            return 0.0;
        }

        $openingBalance = (float) $customer->opening_balance;

        if ($customer->balance_type?->value === 'Cr') {
            $openingBalance *= -1;
        }

        $openInvoices = SalesInvoice::withoutGlobalScopes()
            ->where('customer_id', $customerId)
            ->whereIn('status', [
                InvoiceStatus::Draft->value,
                InvoiceStatus::Posted->value,
                InvoiceStatus::Partial->value,
            ])
            ->sum('total');

        $payments = BankTransaction::withoutGlobalScopes()
            ->where('customer_id', $customerId)
            ->where('type', 'deposit')
            ->sum('amount');

        $returns = SalesReturn::withoutGlobalScopes()
            ->where('customer_id', $customerId)
            ->where('status', SalesReturnStatus::Posted->value)
            ->sum('total');

        return round($openingBalance + (float) $openInvoices - (float) $payments - (float) $returns, 2);
    }

    private static function currentSubtotal(Get $get): float
    {
        return (float) self::calculateTotalsFromData([
            'items' => (array) ($get('items') ?? []),
            'discount' => $get('discount') ?? 0,
        ])['subtotal'];
    }

    private static function currentTax(Get $get): float
    {
        return (float) self::calculateTotalsFromData([
            'items' => (array) ($get('items') ?? []),
            'discount' => $get('discount') ?? 0,
        ])['vat_total'];
    }

    private static function currentAmountDue(Get $get): float
    {
        return (float) self::calculateTotalsFromData([
            'items' => (array) ($get('items') ?? []),
            'discount' => $get('discount') ?? 0,
        ])['total'];
    }

    private static function displayAmountDue(Get $get, ?SalesInvoice $record): float
    {
        $currentTotal = self::currentAmountDue($get);

        if (! $record?->exists) {
            return $currentTotal;
        }

        return round(max(0, $currentTotal - self::invoicePaidAmount($record) - self::invoiceReturnedAmount($record)), 2);
    }

    private static function invoicePaidAmount(?SalesInvoice $invoice): float
    {
        if (! $invoice?->exists) {
            return 0.0;
        }

        return round((float) VoucherAllocation::query()
            ->where('sales_invoice_id', $invoice->id)
            ->whereHas('voucher', fn ($query) => $query->where('status', VoucherStatus::Posted->value))
            ->sum('amount'), 2);
    }

    private static function invoiceReturnedAmount(?SalesInvoice $invoice): float
    {
        if (! $invoice?->exists) {
            return 0.0;
        }

        return round((float) SalesReturn::withoutGlobalScopes()
            ->where('sales_invoice_id', $invoice->id)
            ->where('status', SalesReturnStatus::Posted->value)
            ->sum('total'), 2);
    }

    private static function invoiceOutstandingAmount(SalesInvoice $invoice): float
    {
        return round(max(0, (float) $invoice->total - self::invoicePaidAmount($invoice) - self::invoiceReturnedAmount($invoice)), 2);
    }

    private static function formatMoney(float $amount): string
    {
        return app_money($amount);
    }

    private static function currencySettings(): array
    {
        return app_currency_settings();
    }

    private static function currencySymbol(?array $settings = null): string
    {
        return app_currency_symbol();
    }
}
