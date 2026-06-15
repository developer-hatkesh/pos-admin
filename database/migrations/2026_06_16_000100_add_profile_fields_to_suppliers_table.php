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
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->string('supplier_code')->nullable()->after('company_id');
            $table->string('company_name')->nullable()->after('supplier_code');
            $table->string('contact_person')->nullable()->after('company_name');
            $table->string('mobile_no')->nullable()->after('contact_person');
            $table->string('telephone_no')->nullable()->after('mobile_no');
            $table->string('website')->nullable()->after('email');
            $table->string('tax_number')->nullable()->after('website');
            $table->text('address')->nullable()->after('tax_number');
            $table->string('currency_id', 3)->default('GBP')->after('address');
            $table->foreignId('chart_account_id')->nullable()->after('ledger_id')->constrained('ledgers')->nullOnDelete();
            $table->string('bank_name')->nullable()->after('chart_account_id');
            $table->text('notes')->nullable()->after('bank_name');
        });

        DB::table('suppliers')->orderBy('id')->each(function (object $supplier): void {
            $address = collect([
                $supplier->address_line1 ?? null,
                $supplier->address_line2 ?? null,
                $supplier->city ?? null,
                $supplier->postcode ?? null,
                $supplier->country ?? null,
            ])->filter()->implode(', ');

            DB::table('suppliers')
                ->where('id', $supplier->id)
                ->update([
                    'supplier_code' => sprintf('SUP%03d', $supplier->id),
                    'company_name' => $supplier->name,
                    'telephone_no' => $supplier->phone,
                    'tax_number' => $supplier->vat_number,
                    'address' => $address ?: null,
                    'payment_terms' => match ($supplier->payment_terms ?? null) {
                        'Net7' => 7,
                        'Net14' => 14,
                        'Net30' => 30,
                        default => is_numeric($supplier->payment_terms ?? null) ? (int) $supplier->payment_terms : null,
                    },
                    'chart_account_id' => $supplier->ledger_id,
                ]);
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->unique('supplier_code');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropUnique(['supplier_code']);
            $table->dropConstrainedForeignId('chart_account_id');
            $table->dropColumn([
                'supplier_code',
                'company_name',
                'contact_person',
                'mobile_no',
                'telephone_no',
                'website',
                'tax_number',
                'address',
                'currency_id',
                'bank_name',
                'notes',
            ]);
        });
    }
};
