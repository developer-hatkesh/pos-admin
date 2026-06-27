<?php

declare(strict_types=1);

namespace App\Filament\Resources\VatReports;

use App\Enums\JournalSourceType;
use App\Filament\Resources\Concerns\ResourceHelpers;
use App\Filament\Resources\VatReports\Pages\ListVatReports;
use App\Models\JournalLine;
use App\Models\Ledger;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class VatReportResource extends Resource
{
    use ResourceHelpers;

    protected static ?string $model = Ledger::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'VAT Reports';

    protected static ?string $modelLabel = 'VAT Report';

    protected static ?string $pluralModelLabel = 'VAT Reports';

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('nominal_code', ['2201', '2202', '2200']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('vat_report_name')
                    ->label('Report')
                    ->state(fn (Ledger $record): string => self::reportName($record))
                    ->badge(),
                TextColumn::make('entry_from')
                    ->label('Entry From')
                    ->state(fn (Ledger $record): string => self::entryFrom($record)),
                TextColumn::make('plus_amount')
                    ->label('Plus')
                    ->state(fn (Ledger $record): float => self::plusAmount($record))
                    ->formatStateUsing(fn (mixed $state): string => app_money($state))
                    ->color('success'),
                TextColumn::make('minus_amount')
                    ->label('Minus')
                    ->state(fn (Ledger $record): float => self::minusAmount($record))
                    ->formatStateUsing(fn (mixed $state): string => app_money($state))
                    ->color('danger'),
                TextColumn::make('connected')
                    ->label('Connected')
                    ->state(fn (Ledger $record): string => self::connected($record)),
                TextColumn::make('nominal_code')->label('Ledger Code')->sortable()->toggleable(),
                TextColumn::make('name')->label('Ledger')->searchable()->sortable()->toggleable(),
            ])
            ->defaultSort('nominal_code')
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function salesVatQuery(Builder $query): Builder
    {
        return $query->where('nominal_code', '2201');
    }

    public static function purchaseVatQuery(Builder $query): Builder
    {
        return $query->where('nominal_code', '2202');
    }

    public static function vatReturnQuery(Builder $query): Builder
    {
        return $query->where('nominal_code', '2200');
    }

    private static function reportName(Ledger $ledger): string
    {
        return match ($ledger->nominal_code) {
            '2201' => 'VAT Sales',
            '2202' => 'VAT Purchase',
            default => 'VAT Return',
        };
    }

    private static function entryFrom(Ledger $ledger): string
    {
        return match ($ledger->nominal_code) {
            '2201' => 'Sales Invoice',
            '2202' => 'Purchase Invoice',
            default => 'Auto',
        };
    }

    private static function plusAmount(Ledger $ledger): float
    {
        return match ($ledger->nominal_code) {
            '2201' => self::sourceNet($ledger, JournalSourceType::Sales, credit: true),
            '2202' => self::sourceNet($ledger, JournalSourceType::Purchase, credit: false)
                + self::sourceNet($ledger, JournalSourceType::Expense, credit: false),
            default => self::vatDue($ledger),
        };
    }

    private static function minusAmount(Ledger $ledger): float
    {
        return match ($ledger->nominal_code) {
            '2201' => self::sourceNet($ledger, JournalSourceType::SalesReturn, credit: false),
            '2202' => self::sourceNet($ledger, JournalSourceType::PurchaseReturn, credit: true),
            default => self::adjustments($ledger),
        };
    }

    private static function connected(Ledger $ledger): string
    {
        return $ledger->nominal_code === '2200' ? 'Dashboard' : 'VAT Return';
    }

    private static function sourceNet(Ledger $ledger, JournalSourceType $sourceType, bool $credit): float
    {
        $row = JournalLine::query()
            ->where('ledger_id', $ledger->id)
            ->whereHas('journalEntry', fn (Builder $query): Builder => $query->where('source_type', $sourceType->value))
            ->selectRaw($credit ? 'COALESCE(SUM(credit - debit), 0) as amount' : 'COALESCE(SUM(debit - credit), 0) as amount')
            ->first();

        return round(max(0, (float) $row->amount), 2);
    }

    private static function vatDue(Ledger $ledger): float
    {
        $output = Ledger::query()->where('company_id', $ledger->company_id)->where('nominal_code', '2201')->first();
        $input = Ledger::query()->where('company_id', $ledger->company_id)->where('nominal_code', '2202')->first();

        return round(($output ? self::ledgerNet($output, credit: true) : 0) - ($input ? self::ledgerNet($input, credit: false) : 0), 2);
    }

    private static function adjustments(Ledger $ledger): float
    {
        $output = Ledger::query()->where('company_id', $ledger->company_id)->where('nominal_code', '2201')->first();
        $input = Ledger::query()->where('company_id', $ledger->company_id)->where('nominal_code', '2202')->first();

        $salesReturns = $output ? self::sourceNet($output, JournalSourceType::SalesReturn, credit: false) : 0;
        $purchaseReturns = $input ? self::sourceNet($input, JournalSourceType::PurchaseReturn, credit: true) : 0;

        return round($salesReturns + $purchaseReturns, 2);
    }

    private static function ledgerNet(Ledger $ledger, bool $credit): float
    {
        $row = JournalLine::query()
            ->where('ledger_id', $ledger->id)
            ->selectRaw($credit ? 'COALESCE(SUM(credit - debit), 0) as amount' : 'COALESCE(SUM(debit - credit), 0) as amount')
            ->first();

        return round((float) $row->amount, 2);
    }

    public static function getPages(): array
    {
        return ['index' => ListVatReports::route('/')];
    }
}
