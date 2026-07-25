<?php

declare(strict_types=1);

namespace App\Filament\Resources\JournalVouchers;

use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\JournalVouchers\Pages\CreateJournalVoucher;
use App\Filament\Resources\JournalVouchers\Pages\ListJournalVouchers;
use App\Filament\Resources\JournalVouchers\Pages\ViewJournalVoucher;
use App\Models\JournalVoucher;
use App\Models\Ledger;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
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
                        ->options(['credit_note' => 'Credit Note', 'manual' => 'Manual Journal'])
                        ->default('credit_note')->required()->live()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('sales_return_id', null);
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
                Grid::make(['default' => 1, 'md' => 4])->schema([
                    Placeholder::make('customer_display')->label('Customer')->content(fn (Get $get): string => self::returnValue($get, 'customer')),
                    Placeholder::make('credit_note_date')->label('Credit Note Date')->content(fn (Get $get): string => self::returnValue($get, 'date')),
                    Placeholder::make('credit_note_total')->label('Total Amount')->content(fn (Get $get): string => self::returnValue($get, 'total')),
                    Placeholder::make('credit_note_available')->label('Available')->content(fn (Get $get): string => self::returnValue($get, 'available')),
                ])->visible(fn (Get $get): bool => $get('form_type') === 'credit_note' && filled($get('sales_return_id')))->columnSpanFull(),
                Placeholder::make('accounting_preview')
                    ->label('Accounting Entries (read-only)')
                    ->content(fn (Get $get, ?JournalVoucher $record): HtmlString => self::accountingPreview($get, $record))
                    ->visible(fn (Get $get): bool => $get('form_type') === 'credit_note')
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
                    ->visible(fn (Get $get): bool => $get('form_type') === 'manual'),
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    Placeholder::make('debit_total_display')->label('Total Debit')->content(fn (Get $get): string => app_money(self::lineTotal($get, 'debit'))),
                    Placeholder::make('credit_total_display')->label('Total Credit')->content(fn (Get $get): string => app_money(self::lineTotal($get, 'credit'))),
                    Placeholder::make('difference_display')->label('Difference')->content(fn (Get $get): string => app_money(abs(self::lineTotal($get, 'debit') - self::lineTotal($get, 'credit')))),
                ])->visible(fn (Get $get): bool => $get('form_type') === 'manual')->columnSpanFull(),
                Textarea::make('narration')->required()->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('voucher_no')->label('JV No.')->searchable()->sortable(),
            TextColumn::make('voucher_date')->label('JV Date')->date()->sortable(),
            TextColumn::make('form_type')->label('Form Type')->formatStateUsing(fn (string $state): string => $state === 'credit_note' ? 'Credit Note' : 'Manual Journal')->badge(),
            TextColumn::make('salesReturn.return_no')->label('Source Document')->placeholder('Manual'),
            TextColumn::make('salesReturn.customer.name')->label('Customer')->placeholder('—'),
            TextColumn::make('journalEntry.debit_total')->label('Debit')->formatStateUsing(fn (mixed $state): string => app_money($state ?? 0)),
            TextColumn::make('journalEntry.credit_total')->label('Credit')->formatStateUsing(fn (mixed $state): string => app_money($state ?? 0)),
            TextColumn::make('createdBy.name')->label('Created By'),
        ])->filters([SelectFilter::make('form_type')->options(['credit_note' => 'Credit Note', 'manual' => 'Manual Journal'])])
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

    private static function accountingPreview(Get $get, ?JournalVoucher $record): HtmlString
    {
        $return = SalesReturn::query()
            ->with('journalEntry.journalLines.ledger')
            ->find((int) ($get('sales_return_id') ?: $record?->sales_return_id));

        if (! $return?->journalEntry) {
            return new HtmlString('<span class="text-sm text-gray-500">Select a posted Credit Note to preview its balanced entries.</span>');
        }

        $rows = $return->journalEntry->journalLines->map(fn ($line): string => '<tr class="border-b"><td class="p-2">'.e($line->ledger?->nominal_code).'</td><td class="p-2">'.e($line->ledger?->name).'</td><td class="p-2 text-right">'.e(app_money($line->debit)).'</td><td class="p-2 text-right">'.e(app_money($line->credit)).'</td></tr>')->implode('');

        return new HtmlString('<div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="border-b"><th class="p-2 text-left">Account</th><th class="p-2 text-left">Account Name</th><th class="p-2 text-right">Debit</th><th class="p-2 text-right">Credit</th></tr></thead><tbody>'.$rows.'</tbody><tfoot><tr class="font-semibold"><td class="p-2" colspan="2">Total</td><td class="p-2 text-right">'.e(app_money($return->journalEntry->debit_total)).'</td><td class="p-2 text-right">'.e(app_money($return->journalEntry->credit_total)).'</td></tr></tfoot></table></div>');
    }
}
