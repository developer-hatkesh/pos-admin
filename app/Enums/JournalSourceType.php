<?php

declare(strict_types=1);

namespace App\Enums;

enum JournalSourceType: string
{
    case Sales = 'sales';
    case SalesReturn = 'sales_return';
    case Purchase = 'purchase';
    case Payment = 'payment';
    case Expense = 'expense';
    case Voucher = 'voucher';
    case Bank = 'bank';
    case Manual = 'manual';
    case OpeningBalance = 'opening_balance';
}
