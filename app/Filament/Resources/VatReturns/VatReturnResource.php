<?php

declare(strict_types=1);

namespace App\Filament\Resources\VatReturns;

use App\Enums\VatReturnStatus;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\VatReturns\Pages\ManageVatReturns;
use App\Models\VatReturn;
use App\Services\Accounting\VatReturnService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class VatReturnResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = VatReturn::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'VAT Reports';

    protected static ?string $modelLabel = 'VAT Return';

    protected static ?string $pluralModelLabel = 'VAT Returns';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Period')->schema([
                self::companySelect(),
                DatePicker::make('period_start')->required(),
                DatePicker::make('period_end')->required(),
                Select::make('status')->options(VatReturnStatus::class)->default(VatReturnStatus::Draft)->required(),
                DatePicker::make('submitted_at'),
            ])->columns(3)->columnSpanFull(),
            Section::make('Boxes')->schema([
                self::moneyInput('box1'), self::moneyInput('box2'), self::moneyInput('box4'),
                self::moneyInput('box6'), self::moneyInput('box7'), self::moneyInput('box8'), self::moneyInput('box9'),
            ])->columns(4)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('period_start')->date()->sortable(),
            TextColumn::make('period_end')->date()->sortable(),
            TextColumn::make('box1')->formatStateUsing(fn (mixed $state): string => app_money($state)),
            TextColumn::make('box4')->formatStateUsing(fn (mixed $state): string => app_money($state)),
            TextColumn::make('status')->badge()->sortable(),
        ])->filters([self::statusFilter(VatReturnStatus::class)])
            ->defaultSort('period_end', 'desc')
            ->recordActions([
                Action::make('regenerate')->icon(Heroicon::ArrowPath)->requiresConfirmation()->action(function (VatReturn $record): void {
                    app(VatReturnService::class)->generate($record->company_id, $record->period_start->toDateString(), $record->period_end->toDateString());
                    Notification::make()->title('VAT return generated from journal lines')->success()->send();
                }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageVatReturns::route('/')];
    }
}
