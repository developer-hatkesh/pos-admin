<?php

declare(strict_types=1);

namespace App\Support\Purchases;

use App\Enums\InvoiceStatus;
use App\Enums\PurchaseReturnStatus;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;

final class PurchaseReportSql
{
    public static function openingBalanceSql(string $supplierTable = 'suppliers'): string
    {
        return "(CASE
            WHEN {$supplierTable}.balance_type = 'Dr' THEN -COALESCE({$supplierTable}.opening_balance, 0)
            ELSE COALESCE({$supplierTable}.opening_balance, 0)
        END)";
    }

    public static function purchaseSql(string $supplierTable = 'suppliers'): string
    {
        $statuses = self::quoted([
            InvoiceStatus::Posted->value,
            InvoiceStatus::Paid->value,
            InvoiceStatus::Partial->value,
        ]);

        return "COALESCE((
            SELECT SUM(purchase_invoices.total)
            FROM purchase_invoices
            WHERE purchase_invoices.supplier_id = {$supplierTable}.id
                AND purchase_invoices.status IN ({$statuses})
        ), 0)";
    }

    public static function paymentSql(string $supplierTable = 'suppliers'): string
    {
        return "COALESCE((
            SELECT SUM(vouchers.amount)
            FROM vouchers
            WHERE vouchers.supplier_id = {$supplierTable}.id
                AND vouchers.voucher_type = '".VoucherType::Payment->value."'
                AND vouchers.status = '".VoucherStatus::Posted->value."'
        ), 0)";
    }

    public static function debitNoteSql(string $supplierTable = 'suppliers'): string
    {
        return "COALESCE((
            SELECT SUM(purchase_returns.total)
            FROM purchase_returns
            WHERE purchase_returns.supplier_id = {$supplierTable}.id
                AND purchase_returns.status = '".PurchaseReturnStatus::Posted->value."'
        ), 0)";
    }

    public static function outstandingSql(string $supplierTable = 'suppliers'): string
    {
        return '('.self::openingBalanceSql($supplierTable)
            .' + '.self::purchaseSql($supplierTable)
            .' - '.self::paymentSql($supplierTable)
            .' - '.self::debitNoteSql($supplierTable)
            .')';
    }

    private static function quoted(array $values): string
    {
        return collect($values)
            ->map(fn (string $value): string => "'".str_replace("'", "''", $value)."'")
            ->implode(', ');
    }
}
