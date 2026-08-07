<?php

declare(strict_types=1);

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ManageAuditLogs;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Models\BankTransaction;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\JournalVoucher;
use App\Models\Ledger;
use App\Models\ProductItem;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\Supplier;
use App\Models\Voucher;
use App\Support\CurrentCompany;
use BackedEnum;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

class AuditLogResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Activity Log';

    protected static ?string $pluralModelLabel = 'Activity Logs';

    public static function canViewAny(): bool
    {
        return auth()->user()?->isCompanyAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Activity Log')->schema([
                TextInput::make('causer.name')->label('User')->disabled(),
                TextInput::make('event')->disabled(),
                TextInput::make('description')->disabled()->columnSpanFull(),
                TextInput::make('subject_type')->label('Module')->formatStateUsing(fn (?string $state): string => self::classLabel($state))->disabled(),
                TextInput::make('subject_id')->label('Record ID')->disabled(),
                TextInput::make('created_at')
                    ->formatStateUsing(fn (mixed $state): string => $state?->format('d/m/Y H:i:s') ?? '-')
                    ->disabled(),
                KeyValue::make('properties')->formatStateUsing(fn (mixed $state): array => self::propertiesArray($state))->disabled()->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                $companyId = app(CurrentCompany::class)->id();

                if ($companyId === null) {
                    return $query->whereRaw('1 = 0');
                }

                return $query
                    ->whereHasMorph(
                        'subject',
                        self::companyOwnedSubjectTypes(),
                        fn (Builder $subjectQuery): Builder => $subjectQuery->where('company_id', $companyId),
                    )
                    ->with(['causer', 'subject']);
            })
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('causer.name')->label('User')->placeholder('System')->searchable()->sortable(),
                TextColumn::make('event')->badge()->sortable(),
                TextColumn::make('description')->searchable()->wrap(),
                TextColumn::make('subject_type')
                    ->label('Module')
                    ->formatStateUsing(fn (?string $state): string => self::classLabel($state))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subject_id')->label('Record ID')->sortable(),
                TextColumn::make('properties')
                    ->label('Changed Fields')
                    ->state(fn (Activity $record): string => self::changedFields($record))
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('event')->options([
                    'created' => 'Created',
                    'updated' => 'Updated',
                    'deleted' => 'Deleted',
                    'posted' => 'Posted',
                    'cancelled' => 'Cancelled',
                ]),
                SelectFilter::make('subject_type')
                    ->label('Module')
                    ->options(self::subjectTypeOptions()),
                self::dateRangeFilter('created_at'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAuditLogs::route('/')];
    }

    private static function subjectTypeOptions(): array
    {
        return collect(self::companyOwnedSubjectTypes())
            ->mapWithKeys(fn (string $class): array => [$class => class_basename($class)])
            ->all();
    }

    /** @return array<class-string> */
    private static function companyOwnedSubjectTypes(): array
    {
        return [
            SalesInvoice::class,
            PurchaseInvoice::class,
            SalesReturn::class,
            PurchaseReturn::class,
            Voucher::class,
            JournalVoucher::class,
            Customer::class,
            Supplier::class,
            ProductItem::class,
            BankTransaction::class,
            JournalEntry::class,
            Ledger::class,
        ];
    }

    private static function classLabel(?string $class): string
    {
        return $class ? class_basename($class) : '-';
    }

    private static function changedFields(Activity $activity): string
    {
        $properties = self::propertiesArray($activity->properties);
        $fields = array_keys($properties['attributes'] ?? []);

        return $fields === [] ? '-' : implode(', ', $fields);
    }

    private static function propertiesArray(mixed $properties): array
    {
        if ($properties instanceof Collection) {
            return $properties->toArray();
        }

        return is_array($properties) ? $properties : [];
    }
}
