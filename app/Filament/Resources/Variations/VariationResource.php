<?php

declare(strict_types=1);

namespace App\Filament\Resources\Variations;

use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\Variations\Pages\ManageVariations;
use App\Models\Variation;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class VariationResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = Variation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlusCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Variation';

    protected static ?string $pluralModelLabel = 'Variations';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Variation')->schema([
                self::companySelect(),
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255),
                Repeater::make('types')
                    ->label('Variation Types')
                    ->relationship()
                    ->schema([
                        TextInput::make('name')
                            ->label('Variation Type')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->addActionLabel('Add variation type')
                    ->defaultItems(1)
                    ->minItems(1)
                    ->columns(1)
                    ->columnSpanFull(),
            ])->columns(1)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Variation Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('types.name')
                    ->label('Variation Types')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'types',
                        fn (Builder $query): Builder => $query->where('name', 'like', "%{$search}%"),
                    ))
                    ->listWithLineBreaks()
                    ->limitList(5)
                    ->expandableLimitedList(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('types'))
            ->defaultSort('created_at', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageVariations::route('/')];
    }
}
