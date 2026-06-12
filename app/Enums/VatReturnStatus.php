<?php

declare(strict_types=1);

namespace App\Enums;

enum VatReturnStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
}
