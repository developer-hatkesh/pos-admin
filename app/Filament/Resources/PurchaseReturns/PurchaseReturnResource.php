<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseReturns;

use App\Enums\InvoiceStatus;
use App\Enums\PurchaseReturnStatus;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\PurchaseReturns\Pages\CreatePurchaseReturn;
use App\Filament\Resources\PurchaseReturns\Pages\EditPurchaseReturn;
use App\Filament\Resources\PurchaseReturns\Pages\ListPurchaseReturns;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\TaxRate;
use App\Services\Accounting\PurchaseReturnPostingService;
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
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class PurchaseReturnResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = PurchaseReturn::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Purchases';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Purchase Return';

    protected static ?string $modelLabel = 'Purchase Return';

    protected static ?string $pluralModelLabel = 'Purchase Returns';

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
                            Select::make('supplier_id')
                                ->label('Supplier')
                                ->relationship('supplier', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Set $set): void {
                                    $set('purchase_invoice_id', null);
                                    $set('items', []);
                                    $set('subtotal', 0);
                                    $set('vat_total', 0);
                                    $set('total', 0);
                                }),
                            Select::make('purchase_invoice_id')
                                ->label('Purchase Invoice')
                                ->searchable()
                                ->preload()
                                ->live()
                                ->required()
                                ->options(fn (Get $get): array => self::purchaseInvoiceOptions((int) ($get('supplier_id') ?? 0)))
                                ->afterStateUpdated(function (Set $set): void {
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
                                ->afterStateUpdated(fn (Set $set, mixed $state, ?PurchaseReturn $record = null): null => self::syncReturnNumber($set, $state, $record)),
                            Select::make('status')
                                ->options(self::statusOptions())
                                ->default(PurchaseReturnStatus::Posted->value)
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
                            Placeholder::make('amount_debit_display')
                                ->label(fn (): string => 'Supplier Debit ('.self::currencySymbol().')')
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
                            TableColumn::make('Product')->alignment(Alignment::Center)->width('46%'),
                            TableColumn::make('Rate')->alignment(Alignment::Center)->width('14%'),
                            TableColumn::make('Qty')->alignment(Alignment::Center)->width('7%'),
                            TableColumn::make('Tax %')->alignment(Alignment::Center)->width('13%'),
                            TableColumn::make('Line Total')->alignment(Alignment::Center)->width('14%'),
                        ])
                        ->schema([
                            Grid::make(1)->schema([
                                Select::make('purchase_invoice_item_id')
                                    ->label('Purchase Item')
                                    ->hiddenLabel()
                                    ->options(fn (Get $get): array => self::purchaseInvoiceItemOptions(
                                        (int) ($get('../../purchase_invoice_id') ?? 0),
                                        self::selectedReturnItemIds($get),
                                    ))
                                    ->searchable()
                                    ->live()
                                    ->required()
                                    ->afterStateUpdated(function (Get $get, Set $set, ?int $state): void {
                                        $line = self::purchaseInvoiceItemData($state);

                                        if (! $line) {
                                            return;
                                        }

                                        $qty = self::remainingQty((int) $state, null);
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
                                ->label('Total Debit')
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
            ->columns([
                TextColumn::make('return_no')->searchable()->sortable(),
                TextColumn::make('return_date')->date()->sortable(),
                TextColumn::make('purchaseInvoice.invoice_no')->label('Purchase Invoice')->searchable(),
                TextColumn::make('supplier.name')->searchable(),
                TextColumn::make('total')->formatStateUsing(fn (mixed $state): string => app_money($state))->sortable(),
                TextColumn::make('status')->badge()->sortable(),
            ])
            ->filters([self::statusFilter(PurchaseReturnStatus::class), self::dateRangeFilter('return_date')])
            ->defaultSort('return_date', 'desc')
            ->recordActions([
                Action::make('post')
                    ->icon(Heroicon::CheckCircle)
                    ->requiresConfirmation()
                    ->visible(fn (PurchaseReturn $record): bool => $record->status === PurchaseReturnStatus::Draft)
                    ->action(function (PurchaseReturn $record): void {
                        app(PurchaseReturnPostingService::class)->post($record);
                        Notification::make()->title('Purchase return posted')->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseReturns::route('/'),
            'create' => CreatePurchaseReturn::route('/create'),
            'edit' => EditPurchaseReturn::route('/{record}/edit'),
        ];
    }

    public static function dataFromInvoice(PurchaseInvoice $invoice): array
    {
        $items = $invoice->items
            ->map(fn (PurchaseInvoiceItem $line): array => [
                'purchase_invoice_item_id' => $line->id,
                'product_item_id' => $line->product_item_id,
                'description' => self::lineDescription($line),
                'qty' => self::remainingQty($line->id, null),
                'rate' => $line->rate,
                'tax_rate_id' => $line->tax_rate_id,
                'vat_rate' => $line->vat_rate,
                'vat_amount' => $line->vat_amount,
                'line_total' => $line->line_total,
            ])
            ->filter(fn (array $line): bool => (float) $line['qty'] > 0)
            ->values()
            ->all();

        return self::calculateTotalsFromData([
            'company_id' => $invoice->company_id,
            'return_no' => PurchaseReturn::nextReturnNo($invoice->company_id, today()),
            'purchase_invoice_id' => $invoice->id,
            'supplier_id' => $invoice->supplier_id,
            'return_date' => today()->toDateString(),
            'status' => PurchaseReturnStatus::Posted->value,
            'notes' => 'Return against purchase invoice '.$invoice->invoice_no,
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

    public static function prepareDataForSave(array $data, ?PurchaseReturn $record = null): array
    {
        self::validateUniqueReturnItems($data['items'] ?? []);
        self::validateReturnQuantities($data['items'] ?? [], $record);

        return self::calculateTotalsFromData($data);
    }

    public static function statusOptions(): array
    {
        return [
            PurchaseReturnStatus::Posted->value => 'Posted',
            PurchaseReturnStatus::Cancelled->value => 'Cancelled',
        ];
    }

    private static function purchaseInvoiceOptions(int $supplierId): array
    {
        if ($supplierId < 1) {
            return [];
        }

        return PurchaseInvoice::query()
            ->where('supplier_id', $supplierId)
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

    private static function purchaseInvoiceItemOptions(int $invoiceId, array $excludedLineIds = []): array
    {
        if ($invoiceId < 1) {
            return [];
        }

        return PurchaseInvoiceItem::query()
            ->with('productItem')
            ->where('invoice_id', $invoiceId)
            ->get()
            ->reject(fn (PurchaseInvoiceItem $line): bool => in_array($line->id, $excludedLineIds, true))
            ->filter(fn (PurchaseInvoiceItem $line): bool => self::remainingQty($line->id, null) > 0)
            ->mapWithKeys(fn (PurchaseInvoiceItem $line): array => [
                $line->id => self::lineDescription($line).' (remaining: '.self::remainingQty($line->id, null).')',
            ])
            ->all();
    }

    private static function purchaseInvoiceItemData(?int $lineId): ?array
    {
        if (! $lineId) {
            return null;
        }

        $line = PurchaseInvoiceItem::query()->with('productItem')->find($lineId);

        if (! $line) {
            return null;
        }

        return [
            'product_item_id' => $line->product_item_id,
            'description' => self::lineDescription($line),
            'rate' => (float) $line->rate,
            'tax_rate_id' => $line->tax_rate_id,
            'vat_rate' => (float) $line->vat_rate,
        ];
    }

    private static function selectedReturnItemIds(Get $get): array
    {
        $currentLineId = (int) ($get('purchase_invoice_item_id') ?? 0);
        $items = (array) ($get('../../items') ?? []);

        return collect($items)
            ->pluck('purchase_invoice_item_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0 && $id !== $currentLineId)
            ->unique()
            ->values()
            ->all();
    }

    private static function validateUniqueReturnItems(array $items): void
    {
        $lineIds = collect($items)
            ->pluck('purchase_invoice_item_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0);

        if ($lineIds->count() === $lineIds->unique()->count()) {
            return;
        }

        throw ValidationException::withMessages([
            'items' => 'Each purchase item can only be selected once in a purchase return.',
        ]);
    }

    private static function validateReturnQuantities(array $items, ?PurchaseReturn $record = null): void
    {
        foreach ($items as $item) {
            $lineId = (int) ($item['purchase_invoice_item_id'] ?? 0);
            $qty = (float) ($item['qty'] ?? 0);

            if ($lineId < 1) {
                continue;
            }

            $remaining = self::remainingQty($lineId, $record?->id);

            if ($qty > $remaining) {
                $line = PurchaseInvoiceItem::query()->with('productItem')->find($lineId);

                throw ValidationException::withMessages([
                    'items' => 'Return quantity for '.($line ? self::lineDescription($line) : 'item').' exceeds remaining purchased quantity.',
                ]);
            }
        }
    }

    private static function remainingQty(int $purchaseInvoiceItemId, ?int $currentReturnId): float
    {
        $line = PurchaseInvoiceItem::query()->find($purchaseInvoiceItemId);

        if (! $line) {
            return 0.0;
        }

        $returned = (float) PurchaseReturnItem::query()
            ->where('purchase_invoice_item_id', $purchaseInvoiceItemId)
            ->when($currentReturnId, fn ($query) => $query->where('purchase_return_id', '!=', $currentReturnId))
            ->whereHas('purchaseReturn', fn ($query) => $query->where('status', PurchaseReturnStatus::Posted->value))
            ->sum('qty');

        return round(max(0, (float) $line->qty - $returned), 3);
    }

    private static function lineDescription(PurchaseInvoiceItem $line): string
    {
        return $line->productItem?->name ?: 'Purchase item #'.$line->id;
    }

    private static function nextReturnNumber(mixed $date = null): string
    {
        $companyId = app(CurrentCompany::class)->id();

        return $companyId ? PurchaseReturn::nextReturnNo($companyId, $date) : '';
    }

    private static function syncReturnNumber(Set $set, mixed $date = null, ?PurchaseReturn $record = null): null
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
