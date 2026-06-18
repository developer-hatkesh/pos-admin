<?php

declare(strict_types=1);

namespace App\Enums;

enum StockMovementType: string
{
    case In = 'in';
    case Out = 'out';
    case Adjustment = 'adjustment';
    case Purchase = 'purchase';
    case SalesReturn = 'sales_return';
    case AdjustmentIn = 'adjustment_in';
    case Sale = 'sale';
    case PurchaseReturn = 'purchase_return';
    case AdjustmentOut = 'adjustment_out';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Stock In',
            self::Out => 'Stock Out',
            self::Adjustment => 'Adjustment',
            self::Purchase => 'Purchase',
            self::SalesReturn => 'Sales Return',
            self::AdjustmentIn => 'Adjustment In',
            self::Sale => 'Sale',
            self::PurchaseReturn => 'Purchase Return',
            self::AdjustmentOut => 'Adjustment Out',
        };
    }

    public function increasesStock(): bool
    {
        return in_array($this, [self::In, self::Adjustment, self::Purchase, self::SalesReturn, self::AdjustmentIn], true);
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }
}
