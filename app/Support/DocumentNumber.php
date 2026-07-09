<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

class DocumentNumber
{
    public static function next(string $modelClass, string $column, string $prefix, ?int $companyId, int $padding = 3): string
    {
        if ($companyId === null) {
            return '';
        }

        $fullPrefix = self::prefix($prefix);
        $legacyPrefix = self::legacyPrefix($prefix, $companyId);

        /** @var class-string<Model> $modelClass */
        $numbers = $modelClass::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where(function ($query) use ($column, $fullPrefix, $legacyPrefix): void {
                $query
                    ->where($column, 'like', $fullPrefix.'%')
                    ->orWhere($column, 'like', $legacyPrefix.'%');
            })
            ->pluck($column);

        $next = self::nextCounter($numbers->all(), [$legacyPrefix, $fullPrefix]);

        return $fullPrefix.str_pad((string) $next, $padding, '0', STR_PAD_LEFT);
    }

    public static function nextWhere(string $modelClass, string $column, string $prefix, ?int $companyId, callable $scope, int $padding = 3): string
    {
        if ($companyId === null) {
            return '';
        }

        $fullPrefix = self::prefix($prefix);
        $legacyPrefix = self::legacyPrefix($prefix, $companyId);

        /** @var class-string<Model> $modelClass */
        $query = $modelClass::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where(function ($query) use ($column, $fullPrefix, $legacyPrefix): void {
                $query
                    ->where($column, 'like', $fullPrefix.'%')
                    ->orWhere($column, 'like', $legacyPrefix.'%');
            });

        $scope($query);

        $next = self::nextCounter($query->pluck($column)->all(), [$legacyPrefix, $fullPrefix]);

        return $fullPrefix.str_pad((string) $next, $padding, '0', STR_PAD_LEFT);
    }

    private static function prefix(string $prefix): string
    {
        return $prefix.'-';
    }

    private static function legacyPrefix(string $prefix, int $companyId): string
    {
        return $prefix.'-CL'.$companyId.'-';
    }

    /**
     * @param  array<int, mixed>  $numbers
     * @param  array<int, string>  $prefixes
     */
    private static function nextCounter(array $numbers, array $prefixes): int
    {
        $latest = collect($numbers)
            ->map(fn (mixed $number): ?int => self::extractCounter($number, $prefixes))
            ->filter(fn (?int $counter): bool => $counter !== null)
            ->max();

        return ((int) $latest) + 1;
    }

    /**
     * @param  array<int, string>  $prefixes
     */
    private static function extractCounter(mixed $number, array $prefixes): ?int
    {
        if (! is_string($number)) {
            return null;
        }

        foreach ($prefixes as $prefix) {
            if (! str_starts_with($number, $prefix)) {
                continue;
            }

            $counter = substr($number, strlen($prefix));

            return ctype_digit($counter) ? (int) $counter : null;
        }

        return null;
    }
}
