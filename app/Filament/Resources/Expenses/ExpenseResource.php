<?php

declare(strict_types=1);

namespace App\Filament\Resources\Expenses;

use App\Enums\ExpenseStatus;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\Expenses\Pages\ManageExpenses;
use App\Models\Expense;
use App\Services\Accounting\ExpensePostingService;
use App\Support\CurrentCompany;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
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

class ExpenseResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = Expense::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Expenses';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Expense Entry';

    protected static ?string $modelLabel = 'Expense';

    protected static ?string $pluralModelLabel = 'Expenses';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Expense')->schema([
                self::companySelect(),
                Hidden::make('created_by')->default(fn (): ?int => auth()->id()),
                TextInput::make('voucher_no')
                    ->label('Voucher No')
                    ->default(fn (): string => self::nextVoucherNumber(now()))
                    ->disabled()
                    ->dehydrated(false),
                DatePicker::make('expense_date')
                    ->required()
                    ->default(now())
                    ->live()
                    ->afterStateUpdated(fn (Set $set, mixed $state, ?Expense $record = null): null => self::syncVoucherNumber($set, $state, $record)),
                Select::make('expense_category_id')
                    ->label('Category')
                    ->relationship('category', 'category_name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('supplier_id')->relationship('supplier', 'name')->searchable()->preload(),
                Select::make('status')->options(ExpenseStatus::class)->default(ExpenseStatus::Draft)->required(),
            ])->columns(3)->columnSpanFull(),
            Section::make('Amounts')->schema([
                self::moneyInput('sub_total_amount')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set): null => self::syncGrandTotal($get, $set)),
                self::moneyInput('tax_amount')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set): null => self::syncGrandTotal($get, $set)),
                self::moneyInput('grand_total_amount')->required()->readOnly(),
            ])->columns(3)->columnSpanFull(),
            Section::make('Notes & Attachment')->schema([
                Textarea::make('notes')->rows(3)->columnSpanFull(),
                FileUpload::make('file_path')
                    ->label('File')
                    ->disk('public')
                    ->directory('expenses')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(5120)
                    ->columnSpanFull(),
            ])->columns(1)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('voucher_no')->searchable()->sortable(),
                TextColumn::make('expense_date')->date()->sortable(),
                TextColumn::make('category.category_name')->searchable()->sortable(),
                TextColumn::make('supplier.name')->searchable(),
                TextColumn::make('grand_total_amount')->formatStateUsing(fn (mixed $state): string => app_money($state))->sortable(),
                TextColumn::make('status')->badge()->sortable(),
            ])
            ->filters([self::statusFilter(ExpenseStatus::class), self::dateRangeFilter('expense_date')])
            ->defaultSort('expense_date', 'desc')
            ->recordActions([
                Action::make('post')
                    ->icon(Heroicon::CheckCircle)
                    ->requiresConfirmation()
                    ->visible(fn (Expense $record): bool => $record->status === ExpenseStatus::Draft)
                    ->action(function (Expense $record): void {
                        app(ExpensePostingService::class)->post($record);
                        Notification::make()->title('Expense posted')->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageExpenses::route('/')];
    }

    private static function syncGrandTotal(Get $get, Set $set): null
    {
        $set('grand_total_amount', round((float) ($get('sub_total_amount') ?? 0) + (float) ($get('tax_amount') ?? 0), 2));

        return null;
    }

    private static function nextVoucherNumber(mixed $date = null): string
    {
        $companyId = app(CurrentCompany::class)->id();

        return $companyId ? Expense::nextVoucherNo($companyId, $date) : '';
    }

    private static function syncVoucherNumber(Set $set, mixed $date = null, ?Expense $record = null): null
    {
        if ($record !== null) {
            return null;
        }

        $set('voucher_no', self::nextVoucherNumber($date));

        return null;
    }
}
