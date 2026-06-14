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
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
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

            $table->index(['company_id', 'status']);
        });

        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
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

            $table->index(['company_id', 'status']);
        });

        Schema::create('product_items', function (Blueprint $table): void {
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

        $this->copyExistingRecords();
        $this->addNewForeignKeys();
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            if (Schema::hasColumn('stock_movements', 'product_item_id')) {
                $table->dropConstrainedForeignId('product_item_id');
            }
        });

        Schema::table('bank_transactions', function (Blueprint $table): void {
            if (Schema::hasColumn('bank_transactions', 'customer_id')) {
                $table->dropConstrainedForeignId('customer_id');
            }

            if (Schema::hasColumn('bank_transactions', 'supplier_id')) {
                $table->dropConstrainedForeignId('supplier_id');
            }
        });

        Schema::table('purchase_invoice_items', function (Blueprint $table): void {
            if (Schema::hasColumn('purchase_invoice_items', 'product_item_id')) {
                $table->dropConstrainedForeignId('product_item_id');
            }
        });

        Schema::table('sales_invoice_items', function (Blueprint $table): void {
            if (Schema::hasColumn('sales_invoice_items', 'product_item_id')) {
                $table->dropConstrainedForeignId('product_item_id');
            }
        });

        Schema::table('purchase_invoices', function (Blueprint $table): void {
            if (Schema::hasColumn('purchase_invoices', 'supplier_id')) {
                $table->dropConstrainedForeignId('supplier_id');
            }
        });

        Schema::table('sales_invoices', function (Blueprint $table): void {
            if (Schema::hasColumn('sales_invoices', 'customer_id')) {
                $table->dropConstrainedForeignId('customer_id');
            }
        });

        Schema::dropIfExists('product_items');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('customers');
    }

    private function copyExistingRecords(): void
    {
        if (Schema::hasTable('parties')) {
            DB::table('parties')->where('type', 'customer')->orderBy('id')->each(function (object $party): void {
                DB::table('customers')->updateOrInsert(
                    ['id' => $party->id],
                    [
                        'company_id' => $party->company_id,
                        'name' => $party->name,
                        'phone' => $party->phone,
                        'email' => $party->email,
                        'address_line1' => $party->address_line1,
                        'address_line2' => $party->address_line2,
                        'city' => $party->city,
                        'postcode' => $party->postcode,
                        'country' => $party->country,
                        'vat_number' => $party->vat_number,
                        'payment_terms' => $party->payment_terms,
                        'credit_limit' => $party->credit_limit,
                        'opening_balance' => $party->opening_balance,
                        'balance_type' => $party->balance_type,
                        'ledger_id' => $party->ledger_id,
                        'status' => $party->status,
                        'created_at' => $party->created_at,
                        'updated_at' => $party->updated_at,
                    ],
                );
            });

            DB::table('parties')->where('type', 'supplier')->orderBy('id')->each(function (object $party): void {
                DB::table('suppliers')->updateOrInsert(
                    ['id' => $party->id],
                    [
                        'company_id' => $party->company_id,
                        'name' => $party->name,
                        'phone' => $party->phone,
                        'email' => $party->email,
                        'address_line1' => $party->address_line1,
                        'address_line2' => $party->address_line2,
                        'city' => $party->city,
                        'postcode' => $party->postcode,
                        'country' => $party->country,
                        'vat_number' => $party->vat_number,
                        'payment_terms' => $party->payment_terms,
                        'credit_limit' => $party->credit_limit,
                        'opening_balance' => $party->opening_balance,
                        'balance_type' => $party->balance_type,
                        'ledger_id' => $party->ledger_id,
                        'status' => $party->status,
                        'created_at' => $party->created_at,
                        'updated_at' => $party->updated_at,
                    ],
                );
            });
        }

        if (Schema::hasTable('items')) {
            DB::table('items')->orderBy('id')->each(function (object $item): void {
                DB::table('product_items')->updateOrInsert(
                    ['id' => $item->id],
                    [
                        'company_id' => $item->company_id,
                        'item_code' => $item->item_code,
                        'name' => $item->name,
                        'description' => $item->description,
                        'unit' => $item->unit,
                        'purchase_price' => $item->purchase_price,
                        'sale_price' => $item->sale_price,
                        'vat_rate' => $item->vat_rate,
                        'stock_enabled' => $item->stock_enabled,
                        'opening_stock' => $item->opening_stock,
                        'status' => $item->status,
                        'created_at' => $item->created_at,
                        'updated_at' => $item->updated_at,
                    ],
                );
            });
        }
    }

    private function addNewForeignKeys(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->unsignedBigInteger('party_id')->nullable()->change();
            $table->foreignId('customer_id')->nullable()->after('party_id')->constrained('customers')->nullOnDelete();
            $table->index(['company_id', 'customer_id']);
        });

        Schema::table('purchase_invoices', function (Blueprint $table): void {
            $table->unsignedBigInteger('party_id')->nullable()->change();
            $table->foreignId('supplier_id')->nullable()->after('party_id')->constrained('suppliers')->nullOnDelete();
            $table->index(['company_id', 'supplier_id']);
        });

        Schema::table('sales_invoice_items', function (Blueprint $table): void {
            $table->foreignId('product_item_id')->nullable()->after('item_id')->constrained('product_items')->nullOnDelete();
        });

        Schema::table('purchase_invoice_items', function (Blueprint $table): void {
            $table->foreignId('product_item_id')->nullable()->after('item_id')->constrained('product_items')->nullOnDelete();
        });

        Schema::table('bank_transactions', function (Blueprint $table): void {
            $table->foreignId('customer_id')->nullable()->after('party_id')->constrained('customers')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->after('customer_id')->constrained('suppliers')->nullOnDelete();
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->unsignedBigInteger('item_id')->nullable()->change();
            $table->foreignId('product_item_id')->nullable()->after('item_id')->constrained('product_items')->nullOnDelete();
        });

        $this->copyForeignKeyValues();
    }

    private function copyForeignKeyValues(): void
    {
        DB::table('sales_invoices')
            ->join('parties', 'sales_invoices.party_id', '=', 'parties.id')
            ->where('parties.type', 'customer')
            ->update(['sales_invoices.customer_id' => DB::raw('sales_invoices.party_id')]);

        DB::table('purchase_invoices')
            ->join('parties', 'purchase_invoices.party_id', '=', 'parties.id')
            ->where('parties.type', 'supplier')
            ->update(['purchase_invoices.supplier_id' => DB::raw('purchase_invoices.party_id')]);

        DB::table('sales_invoice_items')->update(['product_item_id' => DB::raw('item_id')]);
        DB::table('purchase_invoice_items')->update(['product_item_id' => DB::raw('item_id')]);
        DB::table('stock_movements')->update(['product_item_id' => DB::raw('item_id')]);

        DB::table('bank_transactions')
            ->join('parties', 'bank_transactions.party_id', '=', 'parties.id')
            ->where('parties.type', 'customer')
            ->update(['bank_transactions.customer_id' => DB::raw('bank_transactions.party_id')]);

        DB::table('bank_transactions')
            ->join('parties', 'bank_transactions.party_id', '=', 'parties.id')
            ->where('parties.type', 'supplier')
            ->update(['bank_transactions.supplier_id' => DB::raw('bank_transactions.party_id')]);
    }
};
