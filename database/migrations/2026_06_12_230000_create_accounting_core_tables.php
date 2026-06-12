<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('postcode')->nullable();
            $table->string('country')->default('UK');
            $table->string('vat_number')->nullable();
            $table->string('currency', 3)->default('GBP');
            $table->date('financial_year_start')->nullable();
            $table->date('financial_year_end')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('role')->default('admin')->after('password')->index();
            $table->string('status')->default('active')->after('role')->index();
        });

        Schema::create('ledgers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('nominal_code');
            $table->string('type')->index();
            $table->foreignId('parent_id')->nullable()->constrained('ledgers')->nullOnDelete();
            $table->boolean('is_control_account')->default(false);
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->string('balance_type', 2)->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->unique(['company_id', 'nominal_code']);
            $table->index(['company_id', 'type']);
        });

        Schema::create('parties', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type')->index();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('postcode')->nullable();
            $table->string('country')->default('UK');
            $table->string('vat_number')->nullable();
            $table->string('payment_terms')->nullable();
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->string('balance_type', 2)->nullable();
            $table->foreignId('ledger_id')->nullable()->constrained('ledgers')->nullOnDelete();
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->index(['company_id', 'type']);
        });

        Schema::create('items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('item_code')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('unit')->default('pcs');
            $table->decimal('purchase_price', 15, 2)->default(0);
            $table->decimal('sale_price', 15, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(20);
            $table->boolean('stock_enabled')->default(true);
            $table->decimal('opening_stock', 15, 3)->default(0);
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->unique(['company_id', 'item_code']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
        Schema::dropIfExists('parties');
        Schema::dropIfExists('ledgers');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn(['role', 'status']);
        });
        Schema::dropIfExists('companies');
    }
};
