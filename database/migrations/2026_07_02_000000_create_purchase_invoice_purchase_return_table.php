<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_invoice_purchase_return')) {
            return;
        }

        if (! Schema::hasTable('purchase_returns') || ! Schema::hasTable('purchase_invoices')) {
            return;
        }

        Schema::create('purchase_invoice_purchase_return', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained('purchase_returns')->cascadeOnDelete();
            $table->foreignId('purchase_invoice_id')->constrained('purchase_invoices')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['purchase_return_id', 'purchase_invoice_id'], 'purchase_return_invoice_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoice_purchase_return');
    }
};
