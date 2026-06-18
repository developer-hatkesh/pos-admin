<?php

declare(strict_types=1);

namespace App\Enums;

enum TaxType: string
{
    case Exclusive = 'exclusive';
    case Inclusive = 'inclusive';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Exclusive => 'Exclusive',
            self::Inclusive => 'Inclusive',
            self::None => 'No Tax',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }
}
