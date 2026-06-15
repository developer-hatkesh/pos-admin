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
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('customer_code')->nullable()->after('company_id');
            $table->string('company_name')->nullable()->after('customer_code');
            $table->string('contact_person')->nullable()->after('company_name');
            $table->string('mobile_no')->nullable()->after('contact_person');
            $table->string('telephone_no')->nullable()->after('mobile_no');
            $table->string('website')->nullable()->after('email');
            $table->string('tax_number')->nullable()->after('website');
            $table->text('billing_address')->nullable()->after('tax_number');
            $table->text('delivery_address')->nullable()->after('billing_address');
            $table->string('currency_id', 3)->default('GBP')->after('delivery_address');
            $table->string('tax_code_id')->nullable()->after('currency_id');
            $table->decimal('discount_percent', 5, 2)->default(0)->after('tax_code_id');
            $table->unsignedInteger('payment_terms_days')->nullable()->after('payment_terms');
            $table->foreignId('chart_account_id')->nullable()->after('ledger_id')->constrained('ledgers')->nullOnDelete();
            $table->text('notes')->nullable()->after('chart_account_id');
        });

        DB::table('customers')->orderBy('id')->each(function (object $customer): void {
            $address = collect([
                $customer->address_line1 ?? null,
                $customer->address_line2 ?? null,
                $customer->city ?? null,
                $customer->postcode ?? null,
                $customer->country ?? null,
            ])->filter()->implode(', ');

            DB::table('customers')
                ->where('id', $customer->id)
                ->update([
                    'customer_code' => sprintf('CUST%03d', $customer->id),
                    'company_name' => $customer->name,
                    'telephone_no' => $customer->phone,
                    'tax_number' => $customer->vat_number,
                    'billing_address' => $address ?: null,
                    'payment_terms_days' => match ($customer->payment_terms ?? null) {
                        'Net7' => 7,
                        'Net14' => 14,
                        'Net30' => 30,
                        default => is_numeric($customer->payment_terms ?? null) ? (int) $customer->payment_terms : null,
                    },
                    'chart_account_id' => $customer->ledger_id,
                ]);
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->unique('customer_code');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique(['customer_code']);
            $table->dropConstrainedForeignId('chart_account_id');
            $table->dropColumn([
                'customer_code',
                'company_name',
                'contact_person',
                'mobile_no',
                'telephone_no',
                'website',
                'tax_number',
                'billing_address',
                'delivery_address',
                'currency_id',
                'tax_code_id',
                'discount_percent',
                'payment_terms_days',
                'notes',
            ]);
        });
    }
};
