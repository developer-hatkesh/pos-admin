<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    protected $fillable = ['name', 'rate'];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
        ];
    }

    public static function defaultId(): int
    {
        return (int) static::query()->where('name', 'Standard')->value('id') ?: 1;
    }

    public static function options(): array
    {
        return static::query()
            ->orderBy('id')
            ->get(['id', 'name', 'rate'])
            ->mapWithKeys(fn (TaxRate $taxRate): array => [
                $taxRate->id => $taxRate->name.' ('.number_format((float) $taxRate->rate, 2).'%)',
            ])
            ->all();
    }

    public static function rateFor(?int $id): float
    {
        if (! $id) {
            return 0.0;
        }

        return (float) static::query()->whereKey($id)->value('rate');
    }

    public static function idForRate(mixed $rate): ?int
    {
        return static::query()
            ->where('rate', round((float) $rate, 2))
            ->orderBy('id')
            ->value('id');
    }
}
