<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductType: string
{
    case Single = 'single';
    case Variation = 'variation';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Single Product',
            self::Variation => 'Variation Product',
            self::Service => 'Service',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }
}
