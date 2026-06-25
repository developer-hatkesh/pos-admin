<?php

declare(strict_types=1);

namespace App\Filament\Resources\Variations;

use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\Variations\Pages\ManageVariations;
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
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class VariationResource extends Resource
{
    use ResourceHelpers;

    public const FORM_MODAL_WIDTH_STYLE = 'max-width: min(calc(100vw - 2rem), 54rem); width: 100%; margin-inline: auto;';

    protected static ?string $model = Variation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlusCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Variation';

    protected static ?string $modelLabel = 'Variation';

    protected static ?string $pluralModelLabel = 'Variations';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            self::companySelect(),
            TextInput::make('name')
                ->label('Name')
                ->placeholder('Enter Name')
                ->required()
                ->maxLength(255)
                ->extraAttributes(['class' => 'variation-name-input'])
                ->columnSpanFull(),
                
            Repeater::make('types')
                ->label('Variation Types')
                ->relationship()
                ->schema([
                    // Forces a side-by-side, clean layout for your repeater elements
                    Grid::make(1)
                        ->schema([
                            TextInput::make('name')
                                ->hiddenLabel()
                                ->placeholder('Please enter variation type')
                                ->required(),
                        ]),
                ])
                ->addActionAlignment(Alignment::Start)
                ->addAction(fn (Action $action): Action => $action
                    ->label('Add variation type')
                    ->icon(Heroicon::Plus)
                    ->iconButton()
                    ->color('primary'))
                ->deleteAction(fn (Action $action): Action => $action
                    ->icon(Heroicon::Trash)
                    ->iconButton()
                    ->color('danger')
                    ->visible(function (array $arguments, Repeater $component): bool {
                        $items = $component->getRawState() ?? [];

                        return array_key_first($items) !== ($arguments['item'] ?? null);
                    }))
                ->defaultItems(1)
                ->minItems(1)
                ->reorderable(false)
                ->cloneable(false)
                ->extraAttributes(['class' => 'variation-types-repeater'])
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
            ->recordActions([
                EditAction::make()
                    ->modalWidth(Width::FourExtraLarge) // Uses Filament standard native scaling
                    ->modalFooterActionsAlignment(Alignment::End),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageVariations::route('/')];
    }
}