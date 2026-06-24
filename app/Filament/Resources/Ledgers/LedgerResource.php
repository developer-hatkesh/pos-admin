<?php

declare(strict_types=1);

namespace App\Filament\Resources\Ledgers;

use App\Enums\BalanceType;
use App\Enums\LedgerType;
use App\Enums\Status;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\Ledgers\Pages\ManageLedgers;
use App\Models\Ledger;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class LedgerResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = Ledger::class;
    protected static bool $shouldRegisterNavigation = false;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;
    protected static string|UnitEnum|null $navigationGroup = 'Other';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'Ledger';
    protected static ?string $modelLabel = 'Ledger';
    protected static ?string $pluralModelLabel = 'Ledgers';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Ledger')->schema([
                self::companySelect(),
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('nominal_code')->required()->maxLength(255),
                Select::make('type')->options(LedgerType::class)->required(),
                Select::make('parent_id')->relationship('parent', 'name')->searchable()->preload(),
                Toggle::make('is_control_account'),
                self::moneyInput('opening_balance'),
                Select::make('balance_type')->options(BalanceType::class),
                Select::make('status')->options(Status::class)->default(Status::Active)->required(),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nominal_code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('type')->badge()->sortable(),
                IconColumn::make('is_control_account')->boolean(),
                TextColumn::make('status')->badge()->sortable(),
            ])
            ->filters([SelectFilter::make('type')->options(LedgerType::class), self::statusFilter(Status::class)])
            ->defaultSort('created_at', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageLedgers::route('/')];
    }
}
