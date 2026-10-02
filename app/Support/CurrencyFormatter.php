<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AppSetting;
use Throwable;

class CurrencyFormatter
{
    public static function options(): array
    {
        return [
            'GBP' => "\u{00A3}",
            'USD' => '$',
            'EUR' => "\u{20AC}",
            'INR' => "\u{20B9}",
            'AED' => "\u{062F}.\u{0625}",
        ];
    }

    public static function settings(?int $companyId = null): array
    {
        $defaults = [
            'currency_default' => 'GBP',
            'currency_decimal_places' => 2,
            'currency_thousands_separator' => ',',
            'currency_decimal_separator' => '.',
            'currency_symbol_right' => false,
        ];

        try {
            $settings = $companyId === null
                ? AppSetting::getValue('currency', [])
                : AppSetting::getValueForCompany('currency', $companyId, []);

            return [...$defaults, ...$settings];
        } catch (Throwable) {
            return $defaults;
        }
    }

    public static function defaultCurrencyCode(?int $companyId = null): string
    {
        $currency = strtoupper((string) (self::settings($companyId)['currency_default'] ?? 'GBP'));

        return array_key_exists($currency, self::options()) ? $currency : 'GBP';
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

    public static function formatCompact(float|int|string|null $amount): string
    {
        return self::formatCompactWithSettings($amount, self::settings());
    }

    public static function formatCompactWithSettings(float|int|string|null $amount, array $settings): string
    {
        $amount = (float) ($amount ?? 0);
        $absoluteAmount = abs($amount);

        $unit = match (true) {
            $absoluteAmount >= 1_000_000_000_000 => ['divisor' => 1_000_000_000_000, 'suffix' => 'T'],
            $absoluteAmount >= 1_000_000_000 => ['divisor' => 1_000_000_000, 'suffix' => 'B'],
            $absoluteAmount >= 1_000_000 => ['divisor' => 1_000_000, 'suffix' => 'M'],
            default => null,
        };

        if ($unit === null) {
            return self::formatWithSettings($amount, $settings);
        }

        $decimalSeparator = (string) $settings['currency_decimal_separator'];
        $compactAmount = number_format($amount / $unit['divisor'], 2, $decimalSeparator, '');
        $compactAmount = rtrim(rtrim($compactAmount, '0'), $decimalSeparator).$unit['suffix'];
        $symbol = self::symbol($settings);

        return $settings['currency_symbol_right'] ? "{$compactAmount} {$symbol}" : "{$symbol} {$compactAmount}";
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
