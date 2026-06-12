<?php

declare(strict_types=1);

namespace App\Enums;

enum LedgerType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Income = 'income';
    case Expense = 'expense';
    case Equity = 'equity';
}
