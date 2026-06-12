<?php

declare(strict_types=1);

namespace App\Enums;

enum AuditAction: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
}
