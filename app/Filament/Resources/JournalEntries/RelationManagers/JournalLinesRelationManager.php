<?php

declare(strict_types=1);

namespace App\Filament\Resources\JournalEntries\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class JournalLinesRelationManager extends RelationManager
{
    protected static string $relationship = 'journalLines';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('ledger_id')->relationship('ledger', 'name')->searchable()->preload()->required(),
            TextInput::make('debit')->numeric()->default(0)->step('0.01'),
            TextInput::make('credit')->numeric()->default(0)->step('0.01'),
            TextInput::make('description')->maxLength(255),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('ledger.nominal_code')->label('Code')->sortable(),
            TextColumn::make('ledger.name')->searchable(),
            TextColumn::make('debit')->money('GBP'),
            TextColumn::make('credit')->money('GBP'),
            TextColumn::make('description')->searchable(),
        ])->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
