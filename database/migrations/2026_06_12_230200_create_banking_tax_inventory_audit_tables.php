<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('bank_name');
            $table->string('account_name');
            $table->string('account_number')->nullable();
            $table->string('sort_code')->nullable();
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });

        Schema::create('bank_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('transaction_date')->index();
            $table->string('type')->index();
            $table->decimal('amount', 15, 2);
            $table->string('reference')->nullable();
            $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->foreignId('ledger_id')->nullable()->constrained('ledgers')->nullOnDelete();
            $table->foreignId('journal_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->boolean('reconciled')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'transaction_date']);
        });

        Schema::create('vat_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('period_start')->index();
            $table->date('period_end')->index();
            $table->decimal('box1', 15, 2)->default(0);
            $table->decimal('box2', 15, 2)->default(0);
            $table->decimal('box4', 15, 2)->default(0);
            $table->decimal('box6', 15, 2)->default(0);
            $table->decimal('box7', 15, 2)->default(0);
            $table->decimal('box8', 15, 2)->default(0);
            $table->decimal('box9', 15, 2)->default(0);
            $table->string('status')->default('draft')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'period_start', 'period_end']);
        });

        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('type')->index();
            $table->decimal('quantity', 15, 3);
            $table->decimal('rate', 15, 2)->default(0);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->date('movement_date')->index();
            $table->timestamp('created_at')->nullable();

            $table->index(['company_id', 'movement_date']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action')->index();
            $table->string('table_name')->index();
            $table->unsignedBigInteger('record_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['company_id', 'table_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('vat_returns');
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('bank_accounts');
    }
};
