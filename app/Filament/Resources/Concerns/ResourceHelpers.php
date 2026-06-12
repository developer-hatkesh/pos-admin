<?php

declare(strict_types=1);

namespace App\Filament\Resources\Concerns;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

trait ResourceHelpers
{
    protected static function companySelect(): Select
    {
        return Select::make('company_id')
            ->relationship('company', 'name')
            ->searchable()
            ->preload()
            ->required();
    }

    protected static function moneyInput(string $name): TextInput
    {
        return TextInput::make($name)->numeric()->default(0)->step('0.01');
    }

    protected static function companyFilter(): SelectFilter
    {
        return SelectFilter::make('company')->relationship('company', 'name')->searchable()->preload();
    }

    protected static function statusFilter(string $enum): SelectFilter
    {
        return SelectFilter::make('status')->options($enum);
    }

    protected static function dateRangeFilter(string $column): Filter
    {
        return Filter::make($column)
            ->schema([
                DatePicker::make('from'),
                DatePicker::make('until'),
            ])
            ->query(fn (Builder $query, array $data): Builder => $query
                ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate($column, '>=', $date))
                ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate($column, '<=', $date)));
    }
}
