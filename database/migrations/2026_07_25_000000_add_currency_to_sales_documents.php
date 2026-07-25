<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->string('currency_id', 3)->nullable()->after('customer_id');
        });

        Schema::table('sales_returns', function (Blueprint $table): void {
            $table->string('currency_id', 3)->nullable()->after('customer_id');
        });

        DB::table('sales_invoices')->orderBy('id')->eachById(function (object $invoice): void {
            $currency = DB::table('customers')->where('id', $invoice->customer_id)->value('currency_id');
            DB::table('sales_invoices')->where('id', $invoice->id)->update(['currency_id' => $currency ?: 'GBP']);
        });

        DB::table('sales_returns')->orderBy('id')->eachById(function (object $return): void {
            $currency = DB::table('sales_invoices')->where('id', $return->sales_invoice_id)->value('currency_id')
                ?: DB::table('customers')->where('id', $return->customer_id)->value('currency_id');
            DB::table('sales_returns')->where('id', $return->id)->update(['currency_id' => $currency ?: 'GBP']);
        });
    }

    public function down(): void
    {
        Schema::table('sales_returns', fn (Blueprint $table) => $table->dropColumn('currency_id'));
        Schema::table('sales_invoices', fn (Blueprint $table) => $table->dropColumn('currency_id'));
    }
};
