<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estimates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('estimate_no');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->date('estimate_date')->index();
            $table->date('expiry_date')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('vat_total', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->string('status')->default('draft')->index();
            $table->foreignId('converted_invoice_id')->nullable()->constrained('sales_invoices')->nullOnDelete();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'estimate_no']);
            $table->index(['company_id', 'customer_id']);
        });

        Schema::create('estimate_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('estimate_id')->constrained('estimates')->cascadeOnDelete();
            $table->foreignId('product_item_id')->nullable()->constrained('product_items')->nullOnDelete();
            $table->string('description')->nullable();
            $table->decimal('qty', 15, 3);
            $table->decimal('rate', 15, 2);
            $table->decimal('vat_rate', 5, 2);
            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            $table->decimal('vat_amount', 15, 2);
            $table->decimal('line_total', 15, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estimate_items');
        Schema::dropIfExists('estimates');
    }
};
