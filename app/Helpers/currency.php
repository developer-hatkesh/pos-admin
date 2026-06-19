<?php

declare(strict_types=1);

use App\Support\CurrencyFormatter;

if (! function_exists('app_money')) {
    function app_money(float|int|string|null $amount): string
    {
        return CurrencyFormatter::format($amount);
    }
}

if (! function_exists('app_currency_symbol')) {
    function app_currency_symbol(): string
    {
        return CurrencyFormatter::symbol();
    }
}

if (! function_exists('app_currency_settings')) {
    function app_currency_settings(): array
    {
        return CurrencyFormatter::settings();
    }
}
