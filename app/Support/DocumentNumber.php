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

        $fullPrefix = self::prefix($prefix, $companyId);

        /** @var class-string<Model> $modelClass */
        $numbers = $modelClass::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where($column, 'like', $fullPrefix.'%')
            ->pluck($column);

        $next = self::nextCounter($numbers->all(), $fullPrefix);

        return $fullPrefix.str_pad((string) $next, $padding, '0', STR_PAD_LEFT);
    }

    public static function nextWhere(string $modelClass, string $column, string $prefix, ?int $companyId, callable $scope, int $padding = 3): string
    {
        if ($companyId === null) {
            return '';
        }

        $fullPrefix = self::prefix($prefix, $companyId);

        /** @var class-string<Model> $modelClass */
        $query = $modelClass::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where($column, 'like', $fullPrefix.'%');

        $scope($query);

        $next = self::nextCounter($query->pluck($column)->all(), $fullPrefix);

        return $fullPrefix.str_pad((string) $next, $padding, '0', STR_PAD_LEFT);
    }

    private static function prefix(string $prefix, int $companyId): string
    {
        return $prefix.'-CL'.$companyId.'-';
    }

    private static function nextCounter(array $numbers, string $prefix): int
    {
        $latest = collect($numbers)
            ->filter(fn (mixed $number): bool => is_string($number) && str_starts_with($number, $prefix))
            ->map(fn (string $number): int => (int) substr($number, strlen($prefix)))
            ->max();

        return ((int) $latest) + 1;
    }
}
