<?php

declare(strict_types=1);

namespace App\Enums;

enum ItemUnit: string
{
    case Pieces = 'pcs';
    case Kilogram = 'kg';
    case Litre = 'ltr';
    case Millilitre = 'ml';
    case Box = 'box';
    case Bottle = 'bottle';
}
