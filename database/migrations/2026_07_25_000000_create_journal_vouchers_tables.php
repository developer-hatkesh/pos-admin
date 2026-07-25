<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_vouchers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('voucher_no');
            $table->date('voucher_date')->index();
            $table->string('form_type')->index();
            $table->foreignId('sales_return_id')->nullable()->constrained('sales_returns')->restrictOnDelete();
            $table->foreignId('journal_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->string('reference')->nullable();
            $table->text('narration')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'voucher_no']);
            $table->unique(['company_id', 'sales_return_id']);
        });

        Schema::create('journal_voucher_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journal_voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_invoice_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->unique(['journal_voucher_id', 'sales_invoice_id'], 'jv_alloc_voucher_invoice_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_voucher_allocations');
        Schema::dropIfExists('journal_vouchers');
    }
};
