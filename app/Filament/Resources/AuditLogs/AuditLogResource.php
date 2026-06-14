<?php

declare(strict_types=1);

namespace App\Filament\Resources\AuditLogs;

use App\Enums\AuditAction;
use App\Filament\Resources\AuditLogs\Pages\ManageAuditLogs;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class AuditLogResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = AuditLog::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;
    protected static string|UnitEnum|null $navigationGroup = 'System';
    protected static ?int $navigationSort = 4;
    protected static ?string $modelLabel = 'Audit Log';
    protected static ?string $pluralModelLabel = 'Audit Logs';

    public static function canCreate(): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Audit Log')->schema([
                TextInput::make('user.name')->disabled(),
                TextInput::make('action')->disabled(),
                TextInput::make('table_name')->disabled(),
                TextInput::make('record_id')->disabled(),
                TextInput::make('ip_address')->disabled(),
                KeyValue::make('old_values')->disabled()->columnSpanFull(),
                KeyValue::make('new_values')->disabled()->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('user.name')->searchable()->sortable(),
            TextColumn::make('action')->badge()->sortable(),
            TextColumn::make('table_name')->searchable()->sortable(),
            TextColumn::make('record_id')->sortable(),
            TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([SelectFilter::make('action')->options(AuditAction::class)])
            ->defaultSort('created_at', 'desc')
            ->recordActions([]);
    }

    public static function getPages(): array { return ['index' => ManageAuditLogs::route('/')]; }
}
