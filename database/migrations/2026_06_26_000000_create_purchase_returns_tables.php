<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_returns')) {
            Schema::create('purchase_returns', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('return_no');
                $table->foreignId('purchase_invoice_id')->constrained('purchase_invoices')->restrictOnDelete();
                $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
                $table->date('return_date')->index();
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->decimal('vat_total', 15, 2)->default(0);
                $table->decimal('total', 15, 2)->default(0);
                $table->string('status')->default('draft')->index();
                $table->text('notes')->nullable();
                $table->foreignId('journal_id')->nullable()->constrained('journal_entries')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['company_id', 'return_no']);
                $table->index(['company_id', 'return_date']);
            });
        }

        if (! Schema::hasTable('purchase_return_items')) {
            Schema::create('purchase_return_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('purchase_return_id')->constrained('purchase_returns')->cascadeOnDelete();
                $table->foreignId('purchase_invoice_item_id')->constrained('purchase_invoice_items')->restrictOnDelete();
                $table->foreignId('product_item_id')->nullable()->constrained('product_items')->nullOnDelete();
                $table->string('description')->nullable();
                $table->decimal('qty', 15, 3);
                $table->decimal('rate', 15, 2);
                $table->decimal('vat_rate', 5, 2)->default(0);
                $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
                $table->decimal('vat_amount', 15, 2)->default(0);
                $table->decimal('line_total', 15, 2)->default(0);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
    }
};
