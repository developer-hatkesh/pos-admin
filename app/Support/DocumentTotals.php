<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\TaxRate;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class DocumentTotals
{
    public static function calculate(array $data, bool $withDiscount = true): array
    {
        $lines = [];
        $subtotalCents = 0;

        foreach (($data['items'] ?? []) as $index => $item) {
            $lineCents = self::lineSubtotalCents($item['qty'] ?? 0, $item['rate'] ?? 0);
            $vatRate = filled($item['tax_rate_id'] ?? null)
                ? TaxRate::rateFor((int) $item['tax_rate_id'])
                : (float) ($item['vat_rate'] ?? 0);

            $lines[$index] = [
                'subtotal_cents' => $lineCents,
                'vat_rate' => $vatRate,
                'rate_key' => self::rateKey($vatRate),
            ];
            $subtotalCents += $lineCents;
        }

        $discountCents = $withDiscount
            ? min(max(self::moneyToCents($data['discount'] ?? 0), 0), $subtotalCents)
            : 0;
        $discountShares = self::allocate($discountCents, array_column($lines, 'subtotal_cents'));
        $taxableByRate = [];
        $lineKeys = array_keys($lines);

        foreach ($lineKeys as $position => $index) {
            $line = &$lines[$index];
            $line['discount_cents'] = $discountShares[$position] ?? 0;
            $line['taxable_cents'] = max(0, $line['subtotal_cents'] - $line['discount_cents']);
            $taxableByRate[$line['rate_key']] = ($taxableByRate[$line['rate_key']] ?? 0) + $line['taxable_cents'];
            unset($line);
        }

        $vatByRate = [];
        foreach ($taxableByRate as $rateKey => $taxableCents) {
            $vatByRate[$rateKey] = BigDecimal::of($taxableCents)
                ->multipliedBy((int) $rateKey)
                ->dividedBy(10000, 0, RoundingMode::HalfUp)
                ->toInt();
        }

        $vatTotalCents = 0;
        foreach ($vatByRate as $rateKey => $groupVatCents) {
            $groupLines = array_filter($lines, fn (array $line): bool => $line['rate_key'] === $rateKey);
            $allocatedVat = self::allocate($groupVatCents, array_column($groupLines, 'taxable_cents'));

            foreach (array_keys($groupLines) as $position => $index) {
                $lines[$index]['vat_cents'] = $allocatedVat[$position] ?? 0;
            }
            $vatTotalCents += $groupVatCents;
        }

        foreach ($lines as $index => $line) {
            $vatCents = $line['vat_cents'] ?? 0;
            $data['items'][$index]['vat_rate'] = $line['vat_rate'];
            $data['items'][$index]['vat_amount'] = self::centsToMoney($vatCents);
            $data['items'][$index]['line_total'] = self::centsToMoney($line['subtotal_cents'] + $vatCents);
        }

        $data['subtotal'] = self::centsToMoney($subtotalCents);
        if ($withDiscount) {
            $data['discount'] = self::centsToMoney($discountCents);
        }
        $data['vat_total'] = self::centsToMoney($vatTotalCents);
        $data['total'] = self::centsToMoney(max(0, $subtotalCents - $discountCents + $vatTotalCents));

        return $data;
    }

    private static function lineSubtotalCents(mixed $quantity, mixed $rate): int
    {
        return BigDecimal::of((string) ($quantity ?: 0))
            ->multipliedBy((string) ($rate ?: 0))
            ->toScale(2, RoundingMode::HalfUp)
            ->multipliedBy(100)
            ->toInt();
    }

    private static function moneyToCents(mixed $amount): int
    {
        return BigDecimal::of((string) ($amount ?: 0))
            ->toScale(2, RoundingMode::HalfUp)
            ->multipliedBy(100)
            ->toInt();
    }

    private static function centsToMoney(int $cents): float
    {
        return $cents / 100;
    }

    private static function rateKey(float $rate): int
    {
        return (int) round(max(0, $rate) * 100, 0, PHP_ROUND_HALF_UP);
    }

    private static function allocate(int $amount, array $weights): array
    {
        $allocation = array_fill(0, count($weights), 0);
        $totalWeight = array_sum($weights);

        if ($amount <= 0 || $totalWeight <= 0 || $weights === []) {
            return $allocation;
        }

        $remainders = [];
        $allocated = 0;
        foreach (array_values($weights) as $position => $weight) {
            $exact = $amount * $weight / $totalWeight;
            $allocation[$position] = (int) floor($exact);
            $remainders[$position] = $exact - $allocation[$position];
            $allocated += $allocation[$position];
        }

        arsort($remainders, SORT_NUMERIC);
        foreach (array_keys($remainders) as $position) {
            if ($allocated >= $amount) {
                break;
            }
            $allocation[$position]++;
            $allocated++;
        }

        return $allocation;
    }
}
