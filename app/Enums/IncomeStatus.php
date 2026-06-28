<?php

declare(strict_types=1);

namespace App\Enums;

enum IncomeStatus: string
{
    case Posted = 'posted';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
}
