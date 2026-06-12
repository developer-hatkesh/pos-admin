<?php

declare(strict_types=1);

namespace App\Enums;

enum BankTransactionType: string
{
    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';
}
