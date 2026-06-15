<?php

declare(strict_types=1);

namespace App\Filament\Resources\Variations;

use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\Variations\Pages\CreateVariation;
use App\Filament\Resources\Variations\Pages\EditVariation;
use App\Filament\Resources\Variations\Pages\ListVariations;
use App\Models\Variation;
use BackedEnum;
use Filament\Actions\Action;
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
use Filament\Support\Enums\Alignment;
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
                    ->placeholder('Enter Name')
                    ->required()
                    ->maxLength(255),
                Repeater::make('types')
                    ->label('Variation Types')
                    ->relationship()
                    ->schema([
                        TextInput::make('name')
                            ->hiddenLabel()
                            ->placeholder('Please enter variation type')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->addActionLabel('')
                    ->addActionAlignment(Alignment::End)
                    ->addAction(fn (Action $action): Action => $action
                        ->icon(Heroicon::Plus)
                        ->iconButton()
                        ->color('primary'))
                    ->deleteAction(fn (Action $action): Action => $action
                        ->icon(Heroicon::Trash)
                        ->iconButton()
                        ->color('danger'))
                    ->defaultItems(1)
                    ->minItems(1)
                    ->reorderable(false)
                    ->cloneable(false)
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                    ->columns(1)
                    ->columnSpanFull(),
            ])
                ->columns(1)
                ->columnSpanFull(),
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
        return [
            'index' => ListVariations::route('/'),
            'create' => CreateVariation::route('/create'),
            'edit' => EditVariation::route('/{record}/edit'),
        ];
    }
}
