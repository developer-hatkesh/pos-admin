<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesReturns;

use App\Enums\SalesReturnStatus;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\SalesReturns\Pages\ManageSalesReturns;
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
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class SalesReturnResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = SalesReturn::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'POS / Sales';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Sales Return';

    protected static ?string $pluralModelLabel = 'Sales Returns';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Return')->schema([
                self::companySelect(),
                Hidden::make('created_by')->default(fn (): ?int => auth()->id()),
                Hidden::make('subtotal')->default(0),
                Hidden::make('vat_total')->default(0),
                Hidden::make('total')->default(0),
                TextInput::make('return_no')->disabled()->dehydrated(false)->placeholder('Auto generated'),
                DatePicker::make('return_date')->required()->default(now()),
                Select::make('sales_invoice_id')
                    ->label('Sales Invoice')
                    ->relationship('salesInvoice', 'invoice_no')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required()
                    ->afterStateUpdated(function (Set $set, ?int $state): void {
                        $invoice = SalesInvoice::query()->find($state);
                        $set('customer_id', $invoice?->customer_id);
                        $set('items', []);
                    }),
                Select::make('customer_id')->relationship('customer', 'name')->searchable()->preload()->required()->disabled()->dehydrated(),
                Select::make('status')->options(SalesReturnStatus::class)->default(SalesReturnStatus::Draft)->required(),
            ])->columns(3)->columnSpanFull(),
            Section::make('Returned Items')->schema([
                Repeater::make('items')
                    ->relationship()
                    ->schema([
                        Select::make('sales_invoice_item_id')
                            ->label('Invoice Item')
                            ->options(fn (Get $get): array => self::invoiceItemOptions((int) ($get('../../sales_invoice_id') ?? 0)))
                            ->searchable()
                            ->live()
                            ->required()
                            ->afterStateUpdated(function (Get $get, Set $set, ?int $state): void {
                                $line = SalesInvoiceItem::query()->find($state);

                                if (! $line) {
                                    return;
                                }

                                $set('product_item_id', $line->product_item_id);
                                $set('description', $line->description);
                                $set('qty', 1);
                                $set('rate', $line->rate);
                                $set('tax_rate_id', $line->tax_rate_id);
                                $set('vat_rate', $line->vat_rate);
                                $set('vat_amount', round((float) $line->rate * ((float) $line->vat_rate / 100), 2));
                                $set('line_total', round((float) $line->rate + ((float) $line->rate * ((float) $line->vat_rate / 100)), 2));
                                self::syncTotals($get, $set, '../../');
                            }),
                        Hidden::make('product_item_id'),
                        TextInput::make('description')->maxLength(255),
                        TextInput::make('qty')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->step('0.001')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set): null => self::syncLine($get, $set)),
                        TextInput::make('rate')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->step('0.01')
                            ->prefix(fn (): string => app_currency_symbol())
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set): null => self::syncLine($get, $set)),
                        Select::make('tax_rate_id')
                            ->label('VAT')
                            ->options(fn (): array => TaxRate::options())
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, ?int $state): null {
                                $set('vat_rate', TaxRate::rateFor($state));

                                return self::syncLine($get, $set);
                            }),
                        Hidden::make('vat_rate')->default(0),
                        TextInput::make('vat_amount')->numeric()->readOnly(),
                        TextInput::make('line_total')->numeric()->readOnly(),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
            ])->columnSpanFull(),
            Section::make('Totals')->schema([
                Placeholder::make('subtotal_display')->label('Subtotal')->content(fn (Get $get): string => app_money(self::totals($get)['subtotal'])),
                Placeholder::make('vat_total_display')->label('VAT')->content(fn (Get $get): string => app_money(self::totals($get)['vat_total'])),
                Placeholder::make('total_display')->label('Total Credit')->content(fn (Get $get): string => app_money(self::totals($get)['total'])),
                Textarea::make('notes')->rows(3)->columnSpanFull(),
            ])->columns(3)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('return_no')->searchable()->sortable(),
                TextColumn::make('return_date')->date()->sortable(),
                TextColumn::make('salesInvoice.invoice_no')->searchable(),
                TextColumn::make('customer.name')->searchable(),
                TextColumn::make('total')->formatStateUsing(fn (mixed $state): string => app_money($state))->sortable(),
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
        return ['index' => ManageSalesReturns::route('/')];
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

    private static function invoiceItemOptions(int $invoiceId): array
    {
        if ($invoiceId < 1) {
            return [];
        }

        return SalesInvoiceItem::query()
            ->where('invoice_id', $invoiceId)
            ->orderBy('description')
            ->get(['id', 'description', 'qty'])
            ->mapWithKeys(fn (SalesInvoiceItem $line): array => [
                $line->id => trim(($line->description ?: 'Item').' (sold: '.(float) $line->qty.')'),
            ])
            ->all();
    }

    private static function syncLine(Get $get, Set $set): null
    {
        $qty = (float) ($get('qty') ?? 0);
        $rate = (float) ($get('rate') ?? 0);
        $vatRate = (float) ($get('vat_rate') ?? 0);
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
}
