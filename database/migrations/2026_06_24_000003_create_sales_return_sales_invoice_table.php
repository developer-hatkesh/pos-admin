<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_return_sales_invoice', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_invoice_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['sales_return_id', 'sales_invoice_id'], 'sales_return_invoice_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_sales_invoice');
    }
};
