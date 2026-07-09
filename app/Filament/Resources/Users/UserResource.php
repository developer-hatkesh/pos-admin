<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users;

use App\Enums\Status;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use App\Support\CurrentCompany;
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
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class UserResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'User';

    protected static ?string $pluralModelLabel = 'Users';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('User')->schema([
                Select::make('company_id')
                    ->label('Default Company')
                    ->relationship(
                        'company',
                        'name',
                        modifyQueryUsing: fn (Builder $query): Builder => self::companyOptionsQuery($query)
                    )
                    ->searchable()
                    ->preload()
                    ->required(false),
                Select::make('companies')
                    ->label('Allowed Companies')
                    ->relationship(
                        'companies',
                        'name',
                        modifyQueryUsing: fn (Builder $query): Builder => self::companyOptionsQuery($query)
                    )
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('roles')
                    ->label('Roles for Current Company')
                    ->relationship(
                        'roles',
                        'name',
                        modifyQueryUsing: fn (Builder $query): Builder => self::roleOptionsQuery($query)
                    )
                    ->multiple()
                    ->searchable()
                    ->preload(),
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('email')->email()->required()->unique(ignoreRecord: true)->maxLength(255),
                TextInput::make('password')->password()->revealable()->dehydrated(fn (?string $state): bool => filled($state))->required(fn (string $operation): bool => $operation === 'create'),
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
                TextColumn::make('roles.name')->label('Roles')->badge(),
                TextColumn::make('status')->badge()->sortable(),
            ])
            ->filters([self::statusFilter(Status::class)])
            ->defaultSort('created_at', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user instanceof User || $user->isSuperAdmin()) {
            return $query;
        }

        $companyIds = app(CurrentCompany::class)->companiesFor($user)->pluck('id')->all();

        return $query->where(function (Builder $query) use ($companyIds): void {
            $query
                ->whereIn('company_id', $companyIds)
                ->orWhereHas('companies', fn (Builder $query): Builder => $query->whereIn('companies.id', $companyIds));
        });
    }

    private static function companyOptionsQuery(Builder $query): Builder
    {
        $user = auth()->user();

        if ($user instanceof User && ! $user->isSuperAdmin()) {
            $query->whereIn('companies.id', app(CurrentCompany::class)->companiesFor($user)->pluck('id'));
        }

        return $query;
    }

    private static function roleOptionsQuery(Builder $query): Builder
    {
        $user = auth()->user();
        $companyId = app(CurrentCompany::class)->id();

        $query->where(function (Builder $query) use ($companyId): void {
            $query->whereNull('roles.company_id');

            if ($companyId !== null) {
                $query->orWhere('roles.company_id', $companyId);
            }
        });

        if (! $user instanceof User || ! $user->isSuperAdmin()) {
            $query->where('roles.name', '!=', config('filament-shield.super_admin.name', 'super_admin'));
        }

        return $query;
    }
}
