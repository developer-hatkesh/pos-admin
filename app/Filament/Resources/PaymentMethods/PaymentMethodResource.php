<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentMethods;

use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\PaymentMethods\Pages\ManagePaymentMethods;
use App\Models\PaymentMethod;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class PaymentMethodResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = PaymentMethod::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;
    protected static string|UnitEnum|null $navigationGroup = 'Accounting';
    protected static ?int $navigationSort = 6;
    protected static ?string $modelLabel = 'Payment Method';
    protected static ?string $pluralModelLabel = 'Payment Methods';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Payment Method')->schema([
                self::companySelect(),
                TextInput::make('name')->required()->maxLength(255),
                Toggle::make('is_enabled')->label('Enabled')->default(true),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company.name')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                ToggleColumn::make('is_enabled')->label('Enabled')->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                self::companyFilter(),
                TernaryFilter::make('is_enabled')->label('Enabled'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePaymentMethods::route('/')];
    }
}
