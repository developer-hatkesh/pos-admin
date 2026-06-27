<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Support\CurrencyFormatter;

class CurrencyService
{
    public static function format(float|int|string|null $amount): string
    {
        return CurrencyFormatter::format($amount);
    }
}
