<?php

declare(strict_types=1);

namespace App\Enums;

enum VoucherType: string
{
    case Payment = 'payment';
    case Receipt = 'receipt';
}
