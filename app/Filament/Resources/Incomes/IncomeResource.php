<?php

declare(strict_types=1);

namespace App\Filament\Resources\Incomes;

use App\Enums\IncomeStatus;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\Incomes\Pages\ManageIncomes;
use App\Models\Income;
use App\Support\CurrentCompany;
use BackedEnum;
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
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class IncomeResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = Income::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Income';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Income Entry';

    protected static ?string $modelLabel = 'Income';

    protected static ?string $pluralModelLabel = 'Incomes';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Income')->schema([
                self::companySelect(),
                Hidden::make('created_by')->default(fn (): ?int => auth()->id()),
                TextInput::make('voucher_no')
                    ->label('Voucher No')
                    ->default(fn (): string => self::nextVoucherNumber(now()))
                    ->disabled()
                    ->dehydrated(false),
                DatePicker::make('income_date')
                    ->label('Income date')
                    ->required()
                    ->default(now())
                    ->live()
                    ->afterStateUpdated(fn (Set $set, mixed $state, ?Income $record = null): null => self::syncVoucherNumber($set, $state, $record)),
                TextInput::make('category')
                    ->label('Category')
                    ->maxLength(255),
                Select::make('status')
                    ->options(self::statusOptions())
                    ->default(IncomeStatus::Posted->value)
                    ->required(),
            ])->columns(3)->columnSpanFull(),
            Section::make('Amounts')->schema([
                self::moneyInput('sub_total_amount')
                    ->label('Sub total')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set): null => self::syncGrandTotal($get, $set)),
                self::moneyInput('tax_amount')
                    ->label('Tax')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set): null => self::syncGrandTotal($get, $set)),
                self::moneyInput('grand_total_amount')
                    ->label('Grand total')
                    ->required()
                    ->readOnly(),
            ])->columns(3)->columnSpanFull(),
            Section::make('Notes & Attachment')->schema([
                Textarea::make('notes')->rows(3)->columnSpanFull(),
                FileUpload::make('attachment_upload')
                    ->label('File')
                    ->disk('s3')
                    ->directory(fn (?Income $record): string => $record === null ? 'incomes/tmp' : "incomes/{$record->getKey()}/incoming")
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(10240)
                    ->openable()
                    ->downloadable()
                    ->deleteUploadedFileUsing(fn (): null => null)
                    ->dehydrated()
                    ->columnSpanFull(),
            ])->columns(1)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('voucher_no')->searchable()->sortable(),
                TextColumn::make('income_date')->label('Income date')->date()->sortable(),
                TextColumn::make('category')->searchable()->sortable()->placeholder('N/A'),
                TextColumn::make('grand_total_amount')
                    ->label('Grand total')
                    ->formatStateUsing(fn (mixed $state): string => app_money($state))
                    ->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('attachment_url')
                    ->label('Attachment')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? 'View file' : 'No file')
                    ->url(fn (Income $record): ?string => $record->attachment_url)
                    ->openUrlInNewTab(),
            ])
            ->filters([self::statusFilter(IncomeStatus::class), self::dateRangeFilter('income_date')])
            ->defaultSort('income_date', 'desc')
            ->recordActions([
                EditAction::make()
                    ->using(function (Income $record, array $data): Income {
                        self::updateRecordWithAttachment($record, $data);

                        return $record;
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageIncomes::route('/')];
    }

    public static function statusOptions(): array
    {
        return [
            IncomeStatus::Posted->value => 'Posted',
            IncomeStatus::Paid->value => 'Paid',
            IncomeStatus::Cancelled->value => 'Cancelled',
        ];
    }

    public static function createRecordWithAttachment(array $data): Income
    {
        $attachmentPaths = self::pullAttachmentPaths($data);
        self::syncGrandTotalFromData($data);

        $record = Income::create($data);
        self::syncAttachment($record, $attachmentPaths);

        return $record;
    }

    public static function updateRecordWithAttachment(Income $record, array $data): void
    {
        $attachmentPaths = self::pullAttachmentPaths($data);
        self::syncGrandTotalFromData($data);

        $record->update($data);
        self::syncAttachment($record, $attachmentPaths);
    }

    private static function syncGrandTotal(Get $get, Set $set): null
    {
        $set('grand_total_amount', round((float) ($get('sub_total_amount') ?? 0) + (float) ($get('tax_amount') ?? 0), 2));

        return null;
    }

    private static function syncGrandTotalFromData(array &$data): void
    {
        $data['grand_total_amount'] = round((float) ($data['sub_total_amount'] ?? 0) + (float) ($data['tax_amount'] ?? 0), 2);
    }

    private static function pullAttachmentPaths(array &$data): array
    {
        $attachmentPaths = Arr::wrap($data['attachment_upload'] ?? []);

        unset($data['attachment_upload']);

        return array_values(array_filter($attachmentPaths, is_string(...)));
    }

    private static function syncAttachment(Income $record, array $selectedPaths): void
    {
        $selectedPath = $selectedPaths[0] ?? null;

        if ($selectedPath !== null && Storage::disk('s3')->exists($selectedPath)) {
            $record
                ->addMediaFromDisk($selectedPath, 's3')
                ->toMediaCollection(Income::ATTACHMENTS_COLLECTION, 's3');
        }

        $record->refresh();
        $record->syncAttachmentUrl();
    }

    private static function nextVoucherNumber(mixed $date = null): string
    {
        $companyId = app(CurrentCompany::class)->id();

        return $companyId ? Income::nextVoucherNo($companyId, $date) : '';
    }

    private static function syncVoucherNumber(Set $set, mixed $date = null, ?Income $record = null): null
    {
        if ($record !== null) {
            return null;
        }

        $set('voucher_no', self::nextVoucherNumber($date));

        return null;
    }
}
