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
        Schema::table('purchase_invoices', function (Blueprint $table): void {
            $table->string('currency_id', 3)->nullable()->after('supplier_id');
        });

        Schema::table('purchase_returns', function (Blueprint $table): void {
            $table->string('currency_id', 3)->nullable()->after('supplier_id');
        });

        DB::table('purchase_invoices')->orderBy('id')->eachById(function (object $invoice): void {
            $currency = DB::table('suppliers')->where('id', $invoice->supplier_id)->value('currency_id');
            DB::table('purchase_invoices')->where('id', $invoice->id)->update(['currency_id' => $currency ?: 'GBP']);
        });

        DB::table('purchase_returns')->orderBy('id')->eachById(function (object $return): void {
            $currency = DB::table('purchase_invoices')->where('id', $return->purchase_invoice_id)->value('currency_id')
                ?: DB::table('suppliers')->where('id', $return->supplier_id)->value('currency_id');
            DB::table('purchase_returns')->where('id', $return->id)->update(['currency_id' => $currency ?: 'GBP']);
        });
    }

    public function down(): void
    {
        Schema::table('purchase_returns', fn (Blueprint $table) => $table->dropColumn('currency_id'));
        Schema::table('purchase_invoices', fn (Blueprint $table) => $table->dropColumn('currency_id'));
    }
};
