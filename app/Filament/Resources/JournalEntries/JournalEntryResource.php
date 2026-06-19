<?php

declare(strict_types=1);

namespace App\Filament\Resources\JournalEntries;

use App\Enums\JournalSourceType;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\JournalEntries\Pages\ManageJournalEntries;
use App\Filament\Resources\JournalEntries\RelationManagers\JournalLinesRelationManager;
use App\Models\JournalEntry;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class JournalEntryResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = JournalEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Accounting';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Journal Entry';

    protected static ?string $pluralModelLabel = 'Journal Entries';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Journal')->schema([
                self::companySelect(),
                DatePicker::make('entry_date')->required()->default(now()),
                TextInput::make('reference')->maxLength(255),
                Select::make('source_type')->options(JournalSourceType::class)->default(JournalSourceType::Manual)->required(),
                TextInput::make('source_id')->numeric(),
                Select::make('created_by')->relationship('createdBy', 'name')->searchable()->preload(),
                Textarea::make('description')->columnSpanFull(),
            ])->columns(3)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('entry_date')->date()->sortable(),
            TextColumn::make('reference')->searchable(),
            TextColumn::make('source_type')->badge()->sortable(),
            TextColumn::make('debit_total')->label('Debit')->formatStateUsing(fn (mixed $state): string => app_money($state)),
            TextColumn::make('credit_total')->label('Credit')->formatStateUsing(fn (mixed $state): string => app_money($state)),
            IconColumn::make('is_balanced')->label('Balanced')->boolean(),
        ])->filters([SelectFilter::make('source_type')->options(JournalSourceType::class), self::dateRangeFilter('entry_date')])
            ->defaultSort('entry_date', 'desc')
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([])]);
    }

    public static function getRelations(): array
    {
        return [JournalLinesRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => ManageJournalEntries::route('/')];
    }
}
