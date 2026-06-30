<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesReturns;

use App\Enums\InvoiceStatus;
use App\Enums\SalesReturnStatus;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\SalesReturns\Pages\CreateSalesReturn;
use App\Filament\Resources\SalesReturns\Pages\EditSalesReturn;
use App\Filament\Resources\SalesReturns\Pages\ListSalesReturns;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SalesReturn;
use App\Models\TaxRate;
use App\Services\Accounting\SalesReturnPostingService;
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
use Illuminate\Validation\ValidationException;
use UnitEnum;

class SalesReturnResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = SalesReturn::class;

    protected static bool $shouldRegisterNavigation = true;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Sales Return';

    protected static ?string $modelLabel = 'Sales Return';

    protected static ?string $pluralModelLabel = 'Sales Returns';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->extraAttributes(['class' => 'sales-invoice-form'])
                ->schema([
                    self::companySelect(),
                    Hidden::make('created_by')->default(fn (): ?int => auth()->id()),
                    Hidden::make('subtotal')->default(0),
                    Hidden::make('vat_total')->default(0),
                    Hidden::make('total')->default(0),
                    Grid::make([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 6,
                    ])->schema([
                        Grid::make(1)->schema([
                            Hidden::make('sales_invoice_id')
                                ->hidden(),
                            Select::make('customer_id')
                                ->label('Customer')
                                ->relationship('customer', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Set $set): void {
                                    $set('sales_invoice_ids', []);
                                    $set('sales_invoice_id', null);
                                    $set('items', []);
                                    $set('subtotal', 0);
                                    $set('vat_total', 0);
                                    $set('total', 0);
                                }),
                            Select::make('sales_invoice_ids')
                                ->label('Sales Invoices')
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->live()
                                ->required()
                                ->options(fn (Get $get): array => self::salesInvoiceOptions((int) ($get('customer_id') ?? 0)))
                                ->afterStateHydrated(function (Select $component, ?SalesReturn $record): void {
                                    if (! $record) {
                                        return;
                                    }

                                    $invoiceIds = $record->salesInvoices()->pluck('sales_invoices.id')->all();

                                    if ($invoiceIds === [] && $record->sales_invoice_id !== null) {
                                        $invoiceIds = [(int) $record->sales_invoice_id];
                                    }

                                    $component->state($invoiceIds);
                                })
                                ->afterStateUpdated(function (Set $set, mixed $state): void {
                                    $invoiceIds = self::normaliseIds($state);
                                    $set('sales_invoice_id', $invoiceIds[0] ?? null);
                                    $set('items', []);
                                    $set('subtotal', 0);
                                    $set('vat_total', 0);
                                    $set('total', 0);
                                }),
                        ])->columnSpan([
                            'default' => 1,
                            'xl' => 2,
                        ]),
                        Grid::make(1)->schema([
                            DatePicker::make('return_date')
                                ->label('Return Date')
                                ->required()
                                ->default(now())
                                ->live()
                                ->afterStateUpdated(fn (Set $set, mixed $state, ?SalesReturn $record = null): null => self::syncReturnNumber($set, $state, $record)),
                            Select::make('status')
                                ->options(self::statusOptions())
                                ->default(SalesReturnStatus::Posted->value)
                                ->required(),
                        ]),
                        Grid::make(1)->schema([
                            TextInput::make('return_no')
                                ->label('Return Number')
                                ->default(fn (): string => self::nextReturnNumber(now()))
                                ->disabled()
                                ->dehydrated(false),
                        ]),
                        Grid::make(1)->schema([
                            Placeholder::make('amount_credit_display')
                                ->label(fn (): string => 'Total Credit ('.self::currencySymbol().')')
                                ->content(fn (Get $get): string => self::formatMoney(self::currentTotal($get)))
                                ->extraAttributes(['class' => 'sales-invoice-form__amount-due']),
                        ])->columnSpan([
                            'default' => 1,
                            'xl' => 2,
                        ]),
                    ])->columnSpanFull(),
                    Repeater::make('items')
                        ->label('')
                        ->relationship()
                        ->table([
                            TableColumn::make('Description')->alignment(Alignment::Center)->width('46%'),
                            TableColumn::make('Rate')->alignment(Alignment::Center)->width('14%'),
                            TableColumn::make('Qty')->alignment(Alignment::Center)->width('7%'),
                            TableColumn::make('Tax %')->alignment(Alignment::Center)->width('13%'),
                            TableColumn::make('Line Total')->alignment(Alignment::Center)->width('14%'),
                        ])
                        ->schema([
                            Grid::make(1)->schema([
                                Select::make('sales_invoice_item_id')
                                    ->label('Invoice Item')
                                    ->hiddenLabel()
                                    ->options(fn (Get $get): array => self::invoiceItemOptions(
                                        self::selectedInvoiceIds($get),
                                        self::selectedReturnItemIds($get),
                                    ))
                                    ->searchable()
                                    ->live()
                                    ->required()
                                    ->afterStateUpdated(function (Get $get, Set $set, ?int $state): void {
                                        $line = self::groupedInvoiceItemData($state, self::selectedInvoiceIds($get));

                                        if (! $line) {
                                            return;
                                        }

                                        $qty = (float) $line['qty'];
                                        $rate = (float) $line['rate'];
                                        $vatRate = (float) $line['vat_rate'];

                                        $set('product_item_id', $line['product_item_id']);
                                        $set('description', $line['description']);
                                        $set('qty', $qty);
                                        $set('rate', $rate);
                                        $set('tax_rate_id', $line['tax_rate_id']);
                                        $set('vat_rate', $vatRate);

                                        self::syncLine($get, $set, $qty, $rate, $vatRate);
                                    }),
                                Textarea::make('description')
                                    ->hiddenLabel()
                                    ->rows(1)
                                    ->maxLength(255),
                            ])->extraAttributes(['class' => 'sales-invoice-form__description-cell']),
                            Hidden::make('product_item_id'),
                            TextInput::make('rate')
                                ->hiddenLabel()
                                ->numeric()
                                ->required()
                                ->default(0)
                                ->step('0.01')
                                ->prefix(fn (): string => self::currencySymbol())
                                ->extraAttributes(['class' => 'sales-invoice-form__centered-field'])
                                ->live()
                                ->afterStateUpdated(fn (Get $get, Set $set): null => self::syncLine($get, $set)),
                            TextInput::make('qty')
                                ->hiddenLabel()
                                ->numeric()
                                ->required()
                                ->default(1)
                                ->step('0.001')
                                ->extraAttributes(['class' => 'sales-invoice-form__centered-field'])
                                ->live()
                                ->afterStateUpdated(fn (Get $get, Set $set): null => self::syncLine($get, $set)),
                            Select::make('tax_rate_id')
                                ->hiddenLabel()
                                ->options(fn (): array => TaxRate::options())
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set, ?int $state): null {
                                    $vatRate = (float) TaxRate::rateFor($state);
                                    $set('vat_rate', $vatRate);

                                    return self::syncLine($get, $set, vatRate: $vatRate);
                                })
                                ->extraAttributes(['class' => 'sales-invoice-form__centered-field']),
                            Hidden::make('vat_rate')->default(0),
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
                        ->columnSpanFull(),
                    Grid::make(1)->schema([
                        Grid::make(1)->schema([
                            Placeholder::make('subtotal_display')
                                ->label('Subtotal')
                                ->inlineLabel()
                                ->content(fn (Get $get): string => self::formatMoney(self::currentSubtotal($get))),
                            Placeholder::make('tax_display')
                                ->label('VAT')
                                ->inlineLabel()
                                ->content(fn (Get $get): string => self::formatMoney(self::currentVat($get))),
                            Placeholder::make('total_display')
                                ->label('Total Credit')
                                ->inlineLabel()
                                ->content(fn (Get $get): string => self::formatMoney(self::currentTotal($get)))
                                ->extraAttributes(['class' => 'sales-invoice-form__total-due']),
                        ])->extraAttributes(['class' => 'sales-invoice-form__totals']),
                    ])->extraAttributes(['class' => 'sales-invoice-form__summary-row'])->columnSpanFull(),
                ])->columns(1)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'items:id,sales_return_id,qty,rate,vat_rate,line_total',
            ]))
            ->columns([
                TextColumn::make('return_no')->searchable()->sortable(),
                TextColumn::make('return_date')->date()->sortable(),
                TextColumn::make('salesInvoice.invoice_no')->label('Sales Invoice')->searchable(),
                TextColumn::make('customer.name')->searchable(),
                TextColumn::make('total')
                    ->state(fn (SalesReturn $record): float => self::displayTotal($record))
                    ->formatStateUsing(fn (mixed $state): string => app_money($state))
                    ->sortable(),
                TextColumn::make('status')->badge()->sortable(),
            ])
            ->filters([self::statusFilter(SalesReturnStatus::class), self::dateRangeFilter('return_date')])
            ->defaultSort('return_date', 'desc')
            ->recordActions([
                Action::make('post')
                    ->icon(Heroicon::CheckCircle)
                    ->requiresConfirmation()
                    ->visible(fn (SalesReturn $record): bool => $record->status === SalesReturnStatus::Draft)
                    ->action(function (SalesReturn $record): void {
                        app(SalesReturnPostingService::class)->post($record);
                        Notification::make()->title('Sales return posted')->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesReturns::route('/'),
            'create' => CreateSalesReturn::route('/create'),
            'edit' => EditSalesReturn::route('/{record}/edit'),
        ];
    }

    public static function dataFromInvoice(SalesInvoice $invoice): array
    {
        $items = $invoice->items
            ->map(fn (SalesInvoiceItem $line): array => [
                'sales_invoice_item_id' => $line->id,
                'product_item_id' => $line->product_item_id,
                'description' => $line->description,
                'qty' => $line->qty,
                'rate' => $line->rate,
                'tax_rate_id' => $line->tax_rate_id,
                'vat_rate' => $line->vat_rate,
                'vat_amount' => $line->vat_amount,
                'line_total' => $line->line_total,
            ])
            ->values()
            ->all();

        return self::calculateTotalsFromData([
            'company_id' => $invoice->company_id,
            'return_no' => SalesReturn::nextReturnNo($invoice->company_id, today()),
            'sales_invoice_id' => $invoice->id,
            'sales_invoice_ids' => [$invoice->id],
            'customer_id' => $invoice->customer_id,
            'return_date' => today()->toDateString(),
            'status' => SalesReturnStatus::Posted->value,
            'notes' => 'Return against invoice '.$invoice->invoice_no,
            'items' => $items,
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

        $data['subtotal'] = round($subtotal, 2);
        $data['vat_total'] = round($vatTotal, 2);
        $data['total'] = round($subtotal + $vatTotal, 2);

        return $data;
    }

    public static function prepareDataForSave(array $data): array
    {
        $invoiceIds = self::normaliseIds($data['sales_invoice_ids'] ?? []);
        $data['sales_invoice_id'] = $invoiceIds[0] ?? ($data['sales_invoice_id'] ?? null);
        unset($data['sales_invoice_ids']);
        self::validateUniqueReturnItems($data['items'] ?? []);

        return self::calculateTotalsFromData($data);
    }

    public static function displayTotal(SalesReturn $return): float
    {
        if (! $return->relationLoaded('items') || $return->items->isEmpty()) {
            return (float) $return->total;
        }

        return round($return->items->sum(function ($line): float {
            $lineTotal = (float) $line->line_total;

            if ($lineTotal > 0) {
                return $lineTotal;
            }

            $net = round((float) $line->qty * (float) $line->rate, 2);
            $vat = round($net * ((float) $line->vat_rate / 100), 2);

            return $net + $vat;
        }), 2);
    }

    public static function selectedSalesInvoiceIdsFromData(array $data): array
    {
        return self::normaliseIds($data['sales_invoice_ids'] ?? []);
    }

    public static function statusOptions(): array
    {
        return [
            SalesReturnStatus::Posted->value => 'Posted',
            SalesReturnStatus::Cancelled->value => 'Cancelled',
        ];
    }

    private static function salesInvoiceOptions(int $customerId): array
    {
        if ($customerId < 1) {
            return [];
        }

        return SalesInvoice::query()
            ->where('customer_id', $customerId)
            ->whereIn('status', [
                InvoiceStatus::Posted->value,
                InvoiceStatus::Partial->value,
                InvoiceStatus::Paid->value,
            ])
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->pluck('invoice_no', 'id')
            ->all();
    }

    private static function invoiceItemOptions(array $invoiceIds, array $excludedLineIds = []): array
    {
        if ($invoiceIds === []) {
            return [];
        }

        $groups = [];

        SalesInvoiceItem::query()
            ->whereIn('invoice_id', $invoiceIds)
            ->orderBy('description')
            ->get()
            ->each(function (SalesInvoiceItem $line) use (&$groups): void {
                $key = self::invoiceItemGroupKey($line);

                if (! isset($groups[$key])) {
                    $groups[$key] = [
                        'id' => $line->id,
                        'description' => $line->description ?: 'Item',
                        'qty' => 0.0,
                    ];
                }

                $groups[$key]['qty'] += (float) $line->qty;
            });

        return collect($groups)
            ->sortBy('description')
            ->reject(fn (array $group): bool => in_array((int) $group['id'], $excludedLineIds, true))
            ->mapWithKeys(fn (array $group): array => [
                $group['id'] => trim($group['description'].' (sold: '.(float) $group['qty'].')'),
            ])
            ->all();
    }

    private static function groupedInvoiceItemData(?int $lineId, array $invoiceIds): ?array
    {
        if (! $lineId || $invoiceIds === []) {
            return null;
        }

        $source = SalesInvoiceItem::query()->find($lineId);

        if (! $source) {
            return null;
        }

        $key = self::invoiceItemGroupKey($source);
        $matchingLines = SalesInvoiceItem::query()
            ->whereIn('invoice_id', $invoiceIds)
            ->get()
            ->filter(fn (SalesInvoiceItem $line): bool => self::invoiceItemGroupKey($line) === $key);

        return [
            'product_item_id' => $source->product_item_id,
            'description' => $source->description,
            'qty' => round((float) $matchingLines->sum('qty'), 3),
            'rate' => (float) $source->rate,
            'tax_rate_id' => $source->tax_rate_id,
            'vat_rate' => (float) $source->vat_rate,
        ];
    }

    private static function selectedInvoiceIds(Get $get): array
    {
        $invoiceIds = self::normaliseIds($get('../../sales_invoice_ids') ?? []);

        if ($invoiceIds === []) {
            $invoiceIds = self::normaliseIds($get('../../sales_invoice_id') ?? null);
        }

        return $invoiceIds;
    }

    private static function selectedReturnItemIds(Get $get): array
    {
        $currentLineId = (int) ($get('sales_invoice_item_id') ?? 0);
        $items = (array) ($get('../../items') ?? []);

        return collect($items)
            ->pluck('sales_invoice_item_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0 && $id !== $currentLineId)
            ->unique()
            ->values()
            ->all();
    }

    private static function validateUniqueReturnItems(array $items): void
    {
        $lineIds = collect($items)
            ->pluck('sales_invoice_item_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0);

        if ($lineIds->count() === $lineIds->unique()->count()) {
            return;
        }

        throw ValidationException::withMessages([
            'items' => 'Each invoice item can only be selected once in a sales return.',
        ]);
    }

    private static function normaliseIds(mixed $ids): array
    {
        if ($ids === null || $ids === '') {
            return [];
        }

        if (! is_array($ids)) {
            $ids = [$ids];
        }

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $ids),
            static fn (int $id): bool => $id > 0,
        )));
    }

    private static function invoiceItemGroupKey(SalesInvoiceItem $line): string
    {
        return implode('|', [
            (int) ($line->product_item_id ?? 0),
            mb_strtolower(trim((string) $line->description)),
            number_format((float) $line->rate, 2, '.', ''),
            (int) ($line->tax_rate_id ?? 0),
            number_format((float) $line->vat_rate, 2, '.', ''),
        ]);
    }

    private static function nextReturnNumber(mixed $date = null): string
    {
        $companyId = app(\App\Support\CurrentCompany::class)->id();

        return $companyId ? SalesReturn::nextReturnNo($companyId, $date) : '';
    }

    private static function syncReturnNumber(Set $set, mixed $date = null, ?SalesReturn $record = null): null
    {
        if ($record !== null) {
            return null;
        }

        $set('return_no', self::nextReturnNumber($date));

        return null;
    }

    private static function syncLine(Get $get, Set $set, ?float $qty = null, ?float $rate = null, ?float $vatRate = null): null
    {
        $qty ??= (float) ($get('qty') ?? 0);
        $rate ??= (float) ($get('rate') ?? 0);
        $vatRate ??= (float) ($get('vat_rate') ?? 0);
        $net = round($qty * $rate, 2);
        $vat = round($net * ($vatRate / 100), 2);

        $set('vat_amount', $vat);
        $set('line_total', $net + $vat);
        self::syncTotals($get, $set, '../../');

        return null;
    }

    private static function syncTotals(Get $get, Set $set, string $parentPath = ''): null
    {
        $data = self::calculateTotalsFromData(['items' => (array) ($get($parentPath.'items') ?? [])]);

        $set($parentPath.'subtotal', $data['subtotal']);
        $set($parentPath.'vat_total', $data['vat_total']);
        $set($parentPath.'total', $data['total']);

        return null;
    }

    private static function totals(Get $get): array
    {
        return self::calculateTotalsFromData(['items' => (array) ($get('items') ?? [])]);
    }

    private static function currentSubtotal(Get $get): float
    {
        return (float) self::totals($get)['subtotal'];
    }

    private static function currentVat(Get $get): float
    {
        return (float) self::totals($get)['vat_total'];
    }

    private static function currentTotal(Get $get): float
    {
        return (float) self::totals($get)['total'];
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
