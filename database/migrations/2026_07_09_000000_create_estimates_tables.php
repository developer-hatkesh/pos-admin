<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('estimates')) {
            Schema::create('estimates', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id');
                $table->string('estimate_no');
                $table->foreignId('customer_id')->nullable();
                $table->date('estimate_date')->index();
                $table->date('expiry_date')->nullable();
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->decimal('discount', 15, 2)->default(0);
                $table->decimal('vat_total', 15, 2)->default(0);
                $table->decimal('total', 15, 2)->default(0);
                $table->string('status')->default('draft')->index();
                $table->foreignId('converted_invoice_id')->nullable();
                $table->string('reference')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'estimate_no']);
                $table->index(['company_id', 'customer_id']);
            });
        }

        if (! Schema::hasTable('estimate_items')) {
            Schema::create('estimate_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('estimate_id');
                $table->foreignId('product_item_id')->nullable();
                $table->string('description')->nullable();
                $table->decimal('qty', 15, 3);
                $table->decimal('rate', 15, 2);
                $table->decimal('vat_rate', 5, 2);
                $table->foreignId('tax_rate_id')->nullable();
                $table->decimal('vat_amount', 15, 2);
                $table->decimal('line_total', 15, 2);
            });
        }

        $this->addForeignKeys();
    }

    public function down(): void
    {
        Schema::dropIfExists('estimate_items');
        Schema::dropIfExists('estimates');
    }

    private function addForeignKeys(): void
    {
        Schema::table('estimates', function (Blueprint $table): void {
            if (Schema::hasTable('companies')) {
                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            }

            if (Schema::hasTable('customers')) {
                $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            }

            if (Schema::hasTable('sales_invoices')) {
                $table->foreign('converted_invoice_id')->references('id')->on('sales_invoices')->nullOnDelete();
            }
        });

        Schema::table('estimate_items', function (Blueprint $table): void {
            $table->foreign('estimate_id')->references('id')->on('estimates')->cascadeOnDelete();

            if (Schema::hasTable('product_items')) {
                $table->foreign('product_item_id')->references('id')->on('product_items')->nullOnDelete();
            }

            if (Schema::hasTable('tax_rates')) {
                $table->foreign('tax_rate_id')->references('id')->on('tax_rates')->nullOnDelete();
            }
        });
    }
};
