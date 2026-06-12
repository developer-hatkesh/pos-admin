<?php

declare(strict_types=1);

namespace App\Enums;

enum BalanceType: string
{
    case Debit = 'Dr';
    case Credit = 'Cr';
}
