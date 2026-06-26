<?php

declare(strict_types=1);

namespace App\Support\Sales;

use App\Enums\InvoiceStatus;
use App\Enums\SalesReturnStatus;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;

final class SalesReportSql
{
    public static function openingBalanceSql(string $customerTable = 'customers'): string
    {
        return "(CASE
            WHEN {$customerTable}.balance_type = 'Cr' THEN -COALESCE({$customerTable}.opening_balance, 0)
            ELSE COALESCE({$customerTable}.opening_balance, 0)
        END)";
    }

    public static function salesSql(string $customerTable = 'customers'): string
    {
        $statuses = self::quoted([
            InvoiceStatus::Posted->value,
            InvoiceStatus::Paid->value,
            InvoiceStatus::Partial->value,
        ]);

        return "COALESCE((
            SELECT SUM(sales_invoices.total)
            FROM sales_invoices
            WHERE sales_invoices.customer_id = {$customerTable}.id
                AND sales_invoices.status IN ({$statuses})
        ), 0)";
    }

    public static function receiptSql(string $customerTable = 'customers'): string
    {
        return "COALESCE((
            SELECT SUM(vouchers.amount)
            FROM vouchers
            WHERE vouchers.customer_id = {$customerTable}.id
                AND vouchers.voucher_type = '".VoucherType::Receipt->value."'
                AND vouchers.status = '".VoucherStatus::Posted->value."'
        ), 0)";
    }

    public static function creditNoteSql(string $customerTable = 'customers'): string
    {
        return "COALESCE((
            SELECT SUM(sales_returns.total)
            FROM sales_returns
            WHERE sales_returns.customer_id = {$customerTable}.id
                AND sales_returns.status = '".SalesReturnStatus::Posted->value."'
        ), 0)";
    }

    public static function outstandingSql(string $customerTable = 'customers'): string
    {
        return '('.self::openingBalanceSql($customerTable)
            .' + '.self::salesSql($customerTable)
            .' - '.self::receiptSql($customerTable)
            .' - '.self::creditNoteSql($customerTable)
            .')';
    }

    private static function quoted(array $values): string
    {
        return collect($values)
            ->map(fn (string $value): string => "'".str_replace("'", "''", $value)."'")
            ->implode(', ');
    }
}
