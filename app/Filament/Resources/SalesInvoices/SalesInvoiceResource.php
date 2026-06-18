<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesInvoices;

use App\Enums\InvoiceStatus;
use App\Enums\Status;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\SalesInvoices\Pages\CreateSalesInvoice;
use App\Filament\Resources\SalesInvoices\Pages\EditSalesInvoice;
use App\Filament\Resources\SalesInvoices\Pages\ListSalesInvoices;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\ProductItem;
use App\Models\SalesInvoice;
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
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class SalesInvoiceResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = SalesInvoice::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;
    protected static string|UnitEnum|null $navigationGroup = 'POS / Sales';
    protected static ?int $navigationSort = 2;
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
                    'xl' => 5,
                ])->schema([
                    Select::make('customer_id')
                        ->label('Billed To')
                        ->placeholder('Search for a client')
                        ->relationship('customer', 'name')
                        ->searchable()
                        ->preload()
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
                    Grid::make(1)->schema([
                        DatePicker::make('invoice_date')
                            ->label('Date of Issue')
                            ->required()
                            ->default(now()),
                        DatePicker::make('due_date')
                            ->label('Due Date'),
                    ]),
                    Grid::make(1)->schema([
                        TextInput::make('invoice_no')
                            ->label('Invoice Number')
                            ->required()
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
                    Placeholder::make('amount_due_display')
                        ->label(fn (): string => 'Amount Due ('.self::currencySymbol().')')
                        ->content(fn (Get $get): string => self::formatMoney(self::currentAmountDue($get)))
                        ->extraAttributes(['class' => 'sales-invoice-form__amount-due']),
                ])->columnSpanFull(),
                Repeater::make('items')
                    ->label('')
                    ->relationship()
                    ->table([
                        TableColumn::make('Description')->width('52%'),
                        TableColumn::make('Rate')->alignment(Alignment::End)->width('16%'),
                        TableColumn::make('Qty')->alignment(Alignment::End)->width('10%'),
                        TableColumn::make('Line Total')->alignment(Alignment::End)->width('16%'),
                    ])
                    ->schema([
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

                                $set('description', $product->name);
                                $set('rate', $product->sale_price ?? 0, shouldCallUpdatedHooks: true);
                                $set('vat_rate', $product->vat_rate ?? 20, shouldCallUpdatedHooks: true);
                            }),
                        Hidden::make('description'),
                        TextInput::make('rate')
                            ->hiddenLabel()
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->step('0.01')
                            ->prefix(fn (): string => self::currencySymbol())
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set): null => self::syncLineAndInvoiceTotals($get, $set)),
                        TextInput::make('qty')
                            ->hiddenLabel()
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->step('0.001')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set): null => self::syncLineAndInvoiceTotals($get, $set)),
                        Grid::make(1)->schema([
                            Placeholder::make('line_total_display')
                                ->hiddenLabel()
                                ->content(fn (Get $get): string => self::formatMoney((float) ($get('line_total') ?? 0)))
                                ->extraAttributes(['class' => 'sales-invoice-form__line-total']),
                            TextInput::make('vat_rate')
                                ->label('Tax %')
                                ->numeric()
                                ->required()
                                ->default(20)
                                ->step('0.01')
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Get $get, Set $set): null => self::syncLineAndInvoiceTotals($get, $set)),
                            Hidden::make('vat_amount')->default(0),
                            Hidden::make('line_total')->default(0),
                        ]),
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
                Grid::make([
                    'default' => 1,
                    'lg' => 2,
                ])->schema([
                    Grid::make(1)->schema([
                        Placeholder::make('subtotal_display')
                            ->label('Subtotal')
                            ->content(fn (Get $get): string => self::formatMoney(self::currentSubtotal($get))),
                        TextInput::make('discount')
                            ->label('Discount')
                            ->numeric()
                            ->default(0)
                            ->step('0.01')
                            ->prefix(fn (): string => self::currencySymbol())
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set): null => self::syncInvoiceTotals($get, $set)),
                        Placeholder::make('tax_display')
                            ->label('Tax')
                            ->content(fn (Get $get): string => self::formatMoney(self::currentTax($get))),
                        Placeholder::make('total_display')
                            ->label('Total')
                            ->content(fn (Get $get): string => self::formatMoney(self::currentAmountDue($get))),
                        Placeholder::make('amount_paid_display')
                            ->label('Amount Paid')
                            ->content(self::formatMoney(0)),
                        Placeholder::make('amount_due_summary_display')
                            ->label(fn (): string => 'Amount Due ('.self::currencySymbol().')')
                            ->content(fn (Get $get): string => self::formatMoney(self::currentAmountDue($get)))
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
            $vatRate = (float) ($item['vat_rate'] ?? 0);
            $lineSubtotal = round($qty * $rate, 2);
            $vatAmount = round($lineSubtotal * ($vatRate / 100), 2);

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

    private static function formatMoney(float $amount): string
    {
        $settings = self::currencySettings();
        $symbol = self::currencySymbol($settings);
        $formattedAmount = number_format(
            $amount,
            (int) $settings['currency_decimal_places'],
            (string) $settings['currency_decimal_separator'],
            (string) $settings['currency_thousands_separator'],
        );

        return $settings['currency_symbol_right'] ? "{$formattedAmount} {$symbol}" : "{$symbol} {$formattedAmount}";
    }

    private static function currencySettings(): array
    {
        return [
            'currency_default' => 'GBP',
            'currency_decimal_places' => 2,
            'currency_thousands_separator' => ',',
            'currency_decimal_separator' => '.',
            'currency_symbol_right' => false,
            ...AppSetting::getValue('currency', []),
        ];
    }

    private static function currencySymbol(?array $settings = null): string
    {
        $settings ??= self::currencySettings();

        return match ($settings['currency_default']) {
            'GBP' => "\u{00A3}",
            'USD' => '$',
            'EUR' => "\u{20AC}",
            'INR' => "\u{20B9}",
            'AED' => "\u{062F}.\u{0625}",
            default => (string) $settings['currency_default'],
        };
    }
}
