<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseInvoices;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\PurchaseInvoices\Pages\ManagePurchaseInvoices;
use App\Models\PurchaseInvoice;
use App\Services\Accounting\PurchasePostingService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PurchaseInvoiceResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = PurchaseInvoice::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;
    protected static string|UnitEnum|null $navigationGroup = 'Purchasing';
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'Purchase Invoice';
    protected static ?string $pluralModelLabel = 'Purchase Invoices';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Invoice')->schema([
                self::companySelect(),
                TextInput::make('invoice_no')->required()->maxLength(255),
                Select::make('party_id')->relationship('party', 'name')->searchable()->preload()->required(),
                DatePicker::make('invoice_date')->required()->default(now()),
                DatePicker::make('due_date'),
                Select::make('status')->options(InvoiceStatus::class)->default(InvoiceStatus::Draft)->required(),
            ])->columns(3),
            Section::make('Lines')->schema([
                Repeater::make('items')->relationship()->schema([
                    Select::make('item_id')->relationship('item', 'name')->searchable()->preload(),
                    TextInput::make('qty')->numeric()->required()->default(1)->step('0.001'),
                    TextInput::make('rate')->numeric()->required()->default(0)->step('0.01'),
                    TextInput::make('vat_rate')->numeric()->required()->default(20)->step('0.01'),
                    TextInput::make('vat_amount')->numeric()->default(0)->step('0.01'),
                    TextInput::make('line_total')->numeric()->default(0)->step('0.01'),
                ])->columns(5)->columnSpanFull(),
            ]),
            Section::make('Totals')->schema([
                self::moneyInput('subtotal'),
                self::moneyInput('vat_total'),
                self::moneyInput('total'),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company.name')->searchable()->sortable(),
                TextColumn::make('invoice_no')->searchable()->sortable(),
                TextColumn::make('party.name')->searchable()->sortable(),
                TextColumn::make('invoice_date')->date()->sortable(),
                TextColumn::make('total')->money('GBP')->sortable(),
                TextColumn::make('status')->badge()->sortable(),
            ])
            ->filters([self::companyFilter(), self::statusFilter(InvoiceStatus::class), self::dateRangeFilter('invoice_date')])
            ->defaultSort('invoice_date', 'desc')
            ->recordActions([
                Action::make('post')
                    ->icon(Heroicon::CheckCircle)
                    ->requiresConfirmation()
                    ->visible(fn (PurchaseInvoice $record): bool => $record->status === InvoiceStatus::Draft)
                    ->action(function (PurchaseInvoice $record): void {
                        app(PurchasePostingService::class)->post($record);
                        Notification::make()->title('Purchase invoice posted')->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array { return ['index' => ManagePurchaseInvoices::route('/')]; }
}
