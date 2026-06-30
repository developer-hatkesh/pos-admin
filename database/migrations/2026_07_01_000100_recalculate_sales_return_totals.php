<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_returns') || ! Schema::hasTable('sales_return_items')) {
            return;
        }

        DB::table('sales_returns')
            ->orderBy('id')
            ->each(function (object $return): void {
                $subtotal = 0.0;
                $vatTotal = 0.0;

                DB::table('sales_return_items')
                    ->where('sales_return_id', $return->id)
                    ->orderBy('id')
                    ->each(function (object $line) use (&$subtotal, &$vatTotal): void {
                        $net = round((float) $line->qty * (float) $line->rate, 2);
                        $vat = round($net * ((float) $line->vat_rate / 100), 2);

                        $subtotal += $net;
                        $vatTotal += $vat;

                        DB::table('sales_return_items')
                            ->where('id', $line->id)
                            ->update([
                                'vat_amount' => $vat,
                                'line_total' => $net + $vat,
                            ]);
                    });

                DB::table('sales_returns')
                    ->where('id', $return->id)
                    ->update([
                        'subtotal' => round($subtotal, 2),
                        'vat_total' => round($vatTotal, 2),
                        'total' => round($subtotal + $vatTotal, 2),
                    ]);
            });
    }

    public function down(): void
    {
        //
    }
};
