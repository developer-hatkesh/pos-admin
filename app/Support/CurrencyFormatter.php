<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AppSetting;

class CurrencyFormatter
{
    public static function settings(): array
    {
        return [
            'currency_default' => 'GBP',
            'currency_decimal_places' => 2,
            'currency_thousands_separator' => ',',
            'currency_decimal_separator' => '.',
            'currency_symbol_right' => false,
            ...AppSetting::getValue('currency', []),
        ];
    }

    public static function symbol(?array $settings = null): string
    {
        $settings ??= self::settings();

        return match ($settings['currency_default']) {
            'GBP' => "\u{00A3}",
            'USD' => '$',
            'EUR' => "\u{20AC}",
            'INR' => "\u{20B9}",
            'AED' => "\u{062F}.\u{0625}",
            default => (string) $settings['currency_default'],
        };
    }

    public static function format(float|int|string|null $amount): string
    {
        $settings = self::settings();

        return self::formatWithSettings($amount, $settings);
    }

    public static function formatWithSettings(float|int|string|null $amount, array $settings): string
    {
        $symbol = self::symbol($settings);
        $formattedAmount = number_format(
            (float) ($amount ?? 0),
            (int) $settings['currency_decimal_places'],
            (string) $settings['currency_decimal_separator'],
            (string) $settings['currency_thousands_separator'],
        );

        return $settings['currency_symbol_right'] ? "{$formattedAmount} {$symbol}" : "{$symbol} {$formattedAmount}";
    }
}
