<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\CurrencyFormatter;
use PHPUnit\Framework\TestCase;

class CurrencyFormatterTest extends TestCase
{
    private array $settings = [
        'currency_default' => 'GBP',
        'currency_decimal_places' => 2,
        'currency_thousands_separator' => ',',
        'currency_decimal_separator' => '.',
        'currency_symbol_right' => false,
    ];

    public function test_compact_format_keeps_amounts_below_one_million_in_full(): void
    {
        $this->assertSame('£ 10,500.00', CurrencyFormatter::formatCompactWithSettings(10_500, $this->settings));
    }

    public function test_compact_format_abbreviates_large_amounts(): void
    {
        $this->assertSame('£ 1M', CurrencyFormatter::formatCompactWithSettings(1_000_000, $this->settings));
        $this->assertSame('£ 1.25M', CurrencyFormatter::formatCompactWithSettings(1_250_000, $this->settings));
        $this->assertSame('£ 1B', CurrencyFormatter::formatCompactWithSettings(1_000_000_000, $this->settings));
        $this->assertSame('£ 1T', CurrencyFormatter::formatCompactWithSettings(1_000_000_000_000, $this->settings));
    }

    public function test_compact_format_respects_currency_position_and_separators(): void
    {
        $settings = [
            ...$this->settings,
            'currency_default' => 'EUR',
            'currency_decimal_separator' => ',',
            'currency_symbol_right' => true,
        ];

        $this->assertSame('1,5M €', CurrencyFormatter::formatCompactWithSettings(1_500_000, $settings));
    }
}
