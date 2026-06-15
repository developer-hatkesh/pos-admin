<?php

declare(strict_types=1);

namespace App\Filament\Resources\ChartOfAccounts;

use App\Filament\Resources\ChartOfAccounts\Pages\ManageChartOfAccounts;
use App\Models\ChartOfAccount;
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

class ChartOfAccountResource extends Resource
{
    protected static ?string $model = ChartOfAccount::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;
    protected static string|UnitEnum|null $navigationGroup = 'Accounting';
    protected static ?int $navigationSort = 3;
    protected static ?string $modelLabel = 'Chart of Account';
    protected static ?string $pluralModelLabel = 'Chart of Accounts';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Chart of Account')->schema([
                Select::make('account_category_id')
                    ->label('Account Category')
                    ->relationship('accountCategory', 'account_category_name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('account_code')
                    ->label('Account Code')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('account_name')
                    ->label('Account Name')
                    ->required()
                    ->maxLength(255),
                Select::make('normal_balance_type')
                    ->label('Normal Balance Type')
                    ->options([
                        'DEBIT' => 'DEBIT',
                        'CREDIT' => 'CREDIT',
                    ])
                    ->required(),
                Toggle::make('is_active')->label('Active')->default(true),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('account_id')->label('ID')->sortable(),
                TextColumn::make('accountCategory.account_category_name')->label('Category')->searchable()->sortable(),
                TextColumn::make('account_code')->label('Account Code')->searchable()->sortable(),
                TextColumn::make('account_name')->label('Account Name')->searchable()->sortable(),
                TextColumn::make('normal_balance_type')->label('Normal Balance')->badge()->sortable(),
                IconColumn::make('is_active')->label('Active')->boolean()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('account_category_id')
                    ->label('Account Category')
                    ->relationship('accountCategory', 'account_category_name'),
                SelectFilter::make('normal_balance_type')
                    ->options([
                        'DEBIT' => 'DEBIT',
                        'CREDIT' => 'CREDIT',
                    ]),
            ])
            ->defaultSort('account_id')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageChartOfAccounts::route('/')];
    }
}
