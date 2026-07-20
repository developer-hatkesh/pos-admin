<?php

declare(strict_types=1);

namespace App\Filament\Resources\Estimates;

use App\Enums\EstimateStatus;
use App\Enums\InvoiceStatus;
use App\Enums\Status;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\Estimates\Pages\CreateEstimate;
use App\Filament\Resources\Estimates\Pages\EditEstimate;
use App\Filament\Resources\Estimates\Pages\ListEstimates;
use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\ProductItem;
use App\Models\SalesInvoice;
use App\Models\TaxRate;
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
use Filament\Forms\Components\RichEditor;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use UnitEnum;

class EstimateResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = Estimate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Estimate';

    protected static ?string $pluralModelLabel = 'Estimates';

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
                                        ->default(fn (): ?int => app(CurrentCompany::class)->id()),
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
                                    'price_type' => 'retail',
                                    'status' => Status::Active,
                                ])->getKey()),
                            Placeholder::make('customer_address_display')
                                ->label('Client Address')
                                ->content(fn (Get $get): HtmlString => self::customerAddressDisplay((int) ($get('customer_id') ?? 0)))
                                ->extraAttributes(['class' => 'sales-invoice-form__customer-address']),
                        ])->columnSpan(['default' => 1, 'xl' => 2]),
                        Grid::make(1)->schema([
                            DatePicker::make('estimate_date')
                                ->label('Date of Issue')
                                ->required()
                                ->default(now()),
                            DatePicker::make('expiry_date')
                                ->label('Expiry Date'),
                        ]),
                        Grid::make(1)->schema([
                            TextInput::make('estimate_no')
                                ->label('Estimate Number')
                                ->required()
                                ->default(fn (): string => self::nextEstimateNumber(app(CurrentCompany::class)->id()))
                                ->readOnly()
                                ->maxLength(255),
                            TextInput::make('reference')
                                ->label('Reference')
                                ->placeholder('Enter value (e.g. PO #)')
                                ->maxLength(255),
                        ]),
                        Select::make('status')
                            ->options(self::statusOptions())
                            ->default(EstimateStatus::Posted->value)
                            ->required(),
                        Placeholder::make('amount_due_display')
                            ->label(fn (): string => 'Estimate Total ('.self::currencySymbol().')')
                            ->content(fn (Get $get): string => self::formatMoney(self::currentAmountDue($get)))
                            ->extraAttributes(['class' => 'sales-invoice-form__amount-due']),
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
                                    ->afterStateUpdated(function (Get $get, Set $set, ?int $state): void {
                                        if (! $state) {
                                            return;
                                        }

                                        $product = ProductItem::query()->find($state);

                                        if (! $product) {
                                            return;
                                        }

                                        $set('description', $product->description ?: '');
                                        $set('rate', self::productPriceForCustomer($product, (int) ($get('../../customer_id') ?? 0)), shouldCallUpdatedHooks: true);
                                        $set('tax_rate_id', $product->defaultTaxRateId(), shouldCallUpdatedHooks: true);
                                        $set('vat_rate', $product->defaultVatRate(), shouldCallUpdatedHooks: true);
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
                                ->afterStateUpdated(fn (Get $get, Set $set): null => self::syncLineAndEstimateTotals($get, $set)),
                            TextInput::make('qty')
                                ->hiddenLabel()
                                ->numeric()
                                ->required()
                                ->default(1)
                                ->step('0.001')
                                ->extraAttributes(['class' => 'sales-invoice-form__centered-field'])
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Get $get, Set $set): null => self::syncLineAndEstimateTotals($get, $set)),
                            Select::make('tax_rate_id')
                                ->hiddenLabel()
                                ->options(fn (): array => TaxRate::options())
                                ->default(fn (): int => TaxRate::defaultId())
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set, ?int $state): null {
                                    $set('vat_rate', TaxRate::rateFor($state));

                                    return self::syncLineAndEstimateTotals($get, $set);
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
                        ->afterStateUpdated(fn (Get $get, Set $set): null => self::syncEstimateTotals($get, $set, '../'))
                        ->partiallyRenderAfterActionsCalled(false)
                        ->defaultItems(1)
                        ->minItems(1)
                        ->reorderable()
                        ->compact()
                        ->extraAttributes(['class' => 'sales-invoice-form__lines'])
                        ->columnSpanFull(),
                    RichEditor::make('notes')
                        ->label('Notes')
                        ->placeholder('Add estimate notes')
                        ->columnSpanFull(),
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
                            ->afterStateUpdated(fn (Get $get, Set $set): null => self::syncEstimateTotals($get, $set)),
                        Placeholder::make('net_amount_display')
                            ->label('Net Amount')
                            ->inlineLabel()
                            ->content(fn (Get $get): string => self::formatMoney(self::currentNetAmount($get))),
                        Placeholder::make('tax_display')
                            ->label('Tax')
                            ->inlineLabel()
                            ->content(fn (Get $get): string => self::formatMoney(self::currentTax($get))),
                        Placeholder::make('total_display')
                            ->label(fn (): string => 'Total ('.self::currencySymbol().')')
                            ->inlineLabel()
                            ->content(fn (Get $get): string => self::formatMoney(self::currentAmountDue($get)))
                            ->extraAttributes(['class' => 'sales-invoice-form__total-due']),
                    ])->extraAttributes(['class' => 'sales-invoice-form__totals'])->columnSpanFull(),
                ])->columns(1)->columnSpanFull(),
        ]);
    }

    public static function calculateTotalsFromData(array $data): array
    {
        return SalesInvoiceResource::calculateTotalsFromData($data);
    }

    public static function nextEstimateNumber(?int $companyId): string
    {
        return $companyId ? Estimate::nextEstimateNo($companyId) : '';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('estimate_no')->searchable()->sortable(),
                TextColumn::make('customer.name')->searchable()->sortable(),
                TextColumn::make('estimate_date')->date()->sortable(),
                TextColumn::make('expiry_date')->date()->sortable(),
                TextColumn::make('total')
                    ->state(function (Estimate $record): float {
                        if ((float) $record->total > 0) {
                            return (float) $record->total;
                        }

                        return (float) self::calculateTotalsFromData([
                            'items' => $record->items()->get()->toArray(),
                            'discount' => $record->discount,
                        ])['total'];
                    })
                    ->formatStateUsing(fn (mixed $state): string => self::formatMoney((float) $state))
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (EstimateStatus|string $state): string => self::statusLabel($state))
                    ->sortable(),
                TextColumn::make('convertedInvoice.invoice_no')
                    ->label('Invoice')
                    ->searchable(),
            ])
            ->filters([self::statusFilter(EstimateStatus::class), self::dateRangeFilter('estimate_date')])
            ->defaultSort('estimate_date', 'desc')
            ->recordActions([
                Action::make('print')
                    ->icon(Heroicon::Printer)
                    ->url(fn (Estimate $record): string => route('estimates.print', $record))
                    ->openUrlInNewTab(),
                self::convertToInvoiceAction(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEstimates::route('/'),
            'create' => CreateEstimate::route('/create'),
            'edit' => EditEstimate::route('/{record}/edit'),
        ];
    }

    public static function statusOptions(): array
    {
        return [
            EstimateStatus::Draft->value => 'Draft',
            EstimateStatus::Posted->value => 'Posted',
            EstimateStatus::Sent->value => 'Sent',
            EstimateStatus::Accepted->value => 'Accepted',
            EstimateStatus::Converted->value => 'Converted',
            EstimateStatus::Cancelled->value => 'Cancel',
        ];
    }

    public static function convertToInvoiceAction(): Action
    {
        return Action::make('convert_to_invoice')
            ->label('Convert to Invoice')
            ->icon(Heroicon::ArrowRightCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Convert estimate to invoice')
            ->modalDescription('This will create a draft sales invoice from this estimate.')
            ->visible(fn (Estimate $record): bool => ! in_array($record->status, [EstimateStatus::Converted, EstimateStatus::Cancelled], true))
            ->successRedirectUrl(fn (Estimate $record): string => SalesInvoiceResource::getUrl('edit', [
                'record' => $record->converted_invoice_id,
            ]))
            ->action(function (Estimate $record): void {
                $invoice = self::convertToInvoice($record);

                Notification::make()
                    ->title('Estimate converted to invoice '.$invoice->invoice_no)
                    ->success()
                    ->send();
            });
    }

    public static function convertToInvoice(Estimate $estimate): SalesInvoice
    {
        if ($estimate->converted_invoice_id !== null) {
            return $estimate->convertedInvoice()->firstOrFail();
        }

        return DB::transaction(function () use ($estimate): SalesInvoice {
            self::recalculateStoredTotals($estimate);
            $estimate->load('items');

            $invoice = SalesInvoice::withoutGlobalScopes()->create([
                'company_id' => $estimate->company_id,
                'invoice_no' => SalesInvoice::nextInvoiceNo((int) $estimate->company_id),
                'customer_id' => $estimate->customer_id,
                'invoice_date' => now()->toDateString(),
                'due_date' => $estimate->expiry_date,
                'subtotal' => $estimate->subtotal,
                'discount' => $estimate->discount,
                'vat_total' => $estimate->vat_total,
                'total' => $estimate->total,
                'status' => InvoiceStatus::Posted,
                'payment_note' => $estimate->reference,
                'notes' => trim('Converted from estimate '.$estimate->estimate_no."\n\n".($estimate->notes ?? '')),
            ]);

            foreach ($estimate->items as $item) {
                $invoice->items()->create([
                    'product_item_id' => $item->product_item_id,
                    'description' => $item->description,
                    'qty' => $item->qty,
                    'rate' => $item->rate,
                    'vat_rate' => $item->vat_rate,
                    'tax_rate_id' => $item->tax_rate_id,
                    'vat_amount' => $item->vat_amount,
                    'line_total' => $item->line_total,
                ]);
            }

            $estimate->forceFill([
                'status' => EstimateStatus::Converted,
                'converted_invoice_id' => $invoice->id,
            ])->save();

            return $invoice;
        });
    }

    public static function recalculateStoredTotals(Estimate $estimate): void
    {
        $items = $estimate->items()->get();
        $data = self::calculateTotalsFromData([
            'items' => $items->toArray(),
            'discount' => $estimate->discount,
        ]);

        foreach ($items->values() as $index => $item) {
            $calculatedItem = $data['items'][$index] ?? null;

            if ($calculatedItem === null) {
                continue;
            }

            $item->forceFill([
                'vat_rate' => $calculatedItem['vat_rate'],
                'vat_amount' => $calculatedItem['vat_amount'],
                'line_total' => $calculatedItem['line_total'],
            ])->saveQuietly();
        }

        $estimate->forceFill([
            'subtotal' => $data['subtotal'],
            'discount' => $data['discount'],
            'vat_total' => $data['vat_total'],
            'total' => $data['total'],
        ])->saveQuietly();
    }

    private static function syncLineAndEstimateTotals(Get $get, Set $set): null
    {
        $qty = (float) ($get('qty') ?? 0);
        $rate = (float) ($get('rate') ?? 0);
        $vatRate = (float) ($get('vat_rate') ?? 0);
        $lineSubtotal = round($qty * $rate, 2);
        $vatAmount = round($lineSubtotal * ($vatRate / 100), 2);

        $set('vat_amount', $vatAmount);
        $set('line_total', $lineSubtotal + $vatAmount);

        self::syncEstimateTotals($get, $set, '../../');

        return null;
    }

    private static function syncEstimateTotals(Get $get, Set $set, string $parentPath = ''): null
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

    private static function productPriceForCustomer(ProductItem $product, int $customerId): float
    {
        $priceType = $customerId > 0
            ? Customer::query()->whereKey($customerId)->value('price_type')
            : 'retail';

        if ($priceType === 'wholesale') {
            return (float) $product->wholesale_price;
        }

        return (float) $product->sale_price;
    }

    private static function customerAddressDisplay(int $customerId): HtmlString
    {
        if ($customerId < 1) {
            return new HtmlString('<span class="text-gray-500">Select a client to view address</span>');
        }

        $customer = Customer::query()->find($customerId);

        if (! $customer) {
            return new HtmlString('<span class="text-gray-500">Client not found</span>');
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

    private static function currentNetAmount(Get $get): float
    {
        $data = self::calculateTotalsFromData([
            'items' => (array) ($get('items') ?? []),
            'discount' => $get('discount') ?? 0,
        ]);

        return round(max(0, (float) $data['subtotal'] - (float) $data['discount']), 2);
    }

    private static function currentAmountDue(Get $get): float
    {
        return (float) self::calculateTotalsFromData([
            'items' => (array) ($get('items') ?? []),
            'discount' => $get('discount') ?? 0,
        ])['total'];
    }

    private static function statusLabel(EstimateStatus|string $status): string
    {
        $status = $status instanceof EstimateStatus ? $status->value : $status;

        return self::statusOptions()[$status] ?? ucfirst($status);
    }

    private static function formatMoney(float $amount): string
    {
        return app_money($amount);
    }

    private static function currencySymbol(): string
    {
        return app_currency_symbol();
    }
}
