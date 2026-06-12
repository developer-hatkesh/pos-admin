<?php

declare(strict_types=1);

namespace App\Enums;

enum PartyType: string
{
    case Customer = 'customer';
    case Supplier = 'supplier';
}
