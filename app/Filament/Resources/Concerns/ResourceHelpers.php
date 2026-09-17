<?php

declare(strict_types=1);

namespace App\Filament\Resources\Concerns;

use App\Support\CurrentCompany;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

trait ResourceHelpers
{
    protected static function clientLineAndDocumentTotalsJs(bool $withDiscount = true): string
    {
        $discountCalculation = $withDiscount
            ? "Math.min(Math.max(moneyToCents(\$get('../../discount')), 0), subtotalCents)"
            : '0';

        return str_replace('__DISCOUNT_CALCULATION__', $discountCalculation, <<<'JS'
            const moneyToCents = (value) => Math.round((Number(value) || 0) * 100)
            const qty = Number($get('qty')) || 0
            const rate = Number($get('rate')) || 0
            const vatRate = Math.max(0, Number($get('vat_rate')) || 0)
            const lineSubtotalCents = Math.round(qty * rate * 100)
            const lineVatCents = Math.round(lineSubtotalCents * vatRate / 100)

            $set('vat_amount', lineVatCents / 100)
            $set('line_total', (lineSubtotalCents + lineVatCents) / 100)

            const items = Object.values($get('../../items') || {})
            const subtotalCents = items.reduce(
                (total, item) => total + Math.round((Number(item.qty) || 0) * (Number(item.rate) || 0) * 100),
                0,
            )
            const discountCents = __DISCOUNT_CALCULATION__
            const taxableRatio = subtotalCents > 0
                ? (subtotalCents - discountCents) / subtotalCents
                : 0
            const taxableByRate = {}

            items.forEach((item) => {
                const itemSubtotalCents = Math.round((Number(item.qty) || 0) * (Number(item.rate) || 0) * 100)
                const rateKey = Math.round(Math.max(0, Number(item.vat_rate) || 0) * 100)

                taxableByRate[rateKey] = (taxableByRate[rateKey] || 0) + (itemSubtotalCents * taxableRatio)
            })

            const vatTotalCents = Object.entries(taxableByRate).reduce(
                (total, [rateKey, taxableCents]) => total + Math.round(taxableCents * Number(rateKey) / 10000),
                0,
            )
            const shippingCents = Math.max(moneyToCents($get('../../shipping')), 0)
            const totalCents = Math.max(0, subtotalCents - discountCents + vatTotalCents + shippingCents)

            $set('../../subtotal', subtotalCents / 100)
            $set('../../vat_total', vatTotalCents / 100)
            $set('../../total', totalCents / 100)
        JS);
    }

    protected static function clientMoneyState(string $statePath, string $symbol): HtmlString
    {
        $path = json_encode($statePath, JSON_THROW_ON_ERROR);

        return new HtmlString(
            e($symbol).'<span x-text="new Intl.NumberFormat(\'en-GB\', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number($get('.e($path).')) || 0)"></span>',
        );
    }

    protected static function clientOutstandingMoneyState(string $statePath, string $symbol, float $deductions = 0): HtmlString
    {
        $path = json_encode($statePath, JSON_THROW_ON_ERROR);
        $deductions = json_encode(round(max(0, $deductions), 2), JSON_THROW_ON_ERROR);

        return new HtmlString(
            e($symbol).'<span x-text="new Intl.NumberFormat(\'en-GB\', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Math.max(0, (Number($get('.e($path).')) || 0) - '.e($deductions).'))"></span>',
        );
    }

    protected static function clientNetMoneyState(string $symbol): HtmlString
    {
        return new HtmlString(
            e($symbol).'<span x-text="new Intl.NumberFormat(\'en-GB\', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Math.max(0, (Number($get(\'subtotal\')) || 0) - (Number($get(\'discount\')) || 0)))"></span>',
        );
    }

    protected static function companySelect(): Hidden
    {
        return Hidden::make('company_id')
            ->default(fn (): ?int => app(CurrentCompany::class)->id());
    }

    protected static function moneyInput(string $name): TextInput
    {
        return TextInput::make($name)->numeric()->default(0)->step('0.01')->prefix(fn (): string => app_currency_symbol());
    }

    protected static function positiveNumberInputAttributes(): array
    {
        return [
            'onwheel' => 'event.preventDefault(); this.blur()',
            'onkeydown' => "if (event.key === 'ArrowUp' || event.key === 'ArrowDown') event.preventDefault()",
        ];
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
