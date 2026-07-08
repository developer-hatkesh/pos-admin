<?php

declare(strict_types=1);

namespace App\Enums;

enum EstimateStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Converted = 'converted';
    case Cancelled = 'cancelled';
}
