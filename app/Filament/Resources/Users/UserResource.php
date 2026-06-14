<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users;

use App\Enums\Status;
use App\Enums\UserRole;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
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

class UserResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = User::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;
    protected static string|UnitEnum|null $navigationGroup = 'System';
    protected static ?int $navigationSort = 2;
    protected static ?string $modelLabel = 'User';
    protected static ?string $pluralModelLabel = 'Users';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('User')->schema([
                self::companySelect()->required(false),
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('email')->email()->required()->unique(ignoreRecord: true)->maxLength(255),
                TextInput::make('password')->password()->revealable()->dehydrated(fn (?string $state): bool => filled($state))->required(fn (string $operation): bool => $operation === 'create'),
                Select::make('role')->options(UserRole::class)->default(UserRole::Viewer)->required(),
                Select::make('status')->options(Status::class)->default(Status::Active)->required(),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('role')->badge()->sortable(),
                TextColumn::make('status')->badge()->sortable(),
            ])
            ->filters([self::statusFilter(Status::class)])
            ->defaultSort('created_at', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array { return ['index' => ManageUsers::route('/')]; }
}
