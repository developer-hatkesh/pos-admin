<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentTerms: string
{
    case Net7 = 'Net7';
    case Net14 = 'Net14';
    case Net30 = 'Net30';
}
