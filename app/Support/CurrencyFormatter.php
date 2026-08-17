<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AppSetting;
use Throwable;

class CurrencyFormatter
{
    public static function settings(): array
    {
        $defaults = [
            'currency_default' => 'GBP',
            'currency_decimal_places' => 2,
            'currency_thousands_separator' => ',',
            'currency_decimal_separator' => '.',
            'currency_symbol_right' => false,
        ];

        try {
            return [...$defaults, ...AppSetting::getValue('currency', [])];
        } catch (Throwable) {
            return $defaults;
        }
    }

    public static function symbol(?array $settings = null): string
    {
        $settings ??= self::settings();

        return self::symbolForCode((string) $settings['currency_default']);
    }

    public static function symbolForCode(?string $currency): string
    {
        return match (strtoupper((string) $currency)) {
            'GBP' => "\u{00A3}",
            'USD' => '$',
            'EUR' => "\u{20AC}",
            'INR' => "\u{20B9}",
            'AED' => "\u{062F}.\u{0625}",
            default => strtoupper((string) $currency),
        };
    }

    public static function formatForCurrency(float|int|string|null $amount, ?string $currency): string
    {
        $settings = self::settings();
        $settings['currency_default'] = filled($currency)
            ? strtoupper((string) $currency)
            : $settings['currency_default'];

        return self::formatWithSettings($amount, $settings);
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
