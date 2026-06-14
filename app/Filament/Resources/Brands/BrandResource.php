<?php

declare(strict_types=1);

namespace App\Filament\Resources\Brands;

use App\Enums\Status;
use App\Filament\Resources\Brands\Pages\ManageBrands;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Models\Brand;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class BrandResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = Brand::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;
    protected static string|UnitEnum|null $navigationGroup = 'Catalogue';
    protected static ?int $navigationSort = 2;
    protected static ?string $modelLabel = 'Brand';
    protected static ?string $pluralModelLabel = 'Brands';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Brand')->schema([
                self::companySelect(),
                TextInput::make('name')->required()->maxLength(255),
                Select::make('status')->options(Status::class)->default(Status::Active)->required(),
            ])->columns(1)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('status')->badge()->sortable(),
            TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([self::statusFilter(Status::class)])
            ->defaultSort('created_at', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageBrands::route('/')];
    }
}
