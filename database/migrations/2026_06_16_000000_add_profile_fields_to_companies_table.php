<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('contact_person_name')->nullable()->after('name');
            $table->string('website')->nullable()->after('email');
            $table->text('additional_information')->nullable()->after('website');
            $table->string('legal_business_name')->nullable()->after('currency');
            $table->string('company_house_number')->nullable()->after('vat_number');
            $table->string('business_phone_number')->nullable()->after('company_house_number');
            $table->string('number_of_employees')->nullable()->after('country');
            $table->text('notes')->nullable()->after('financial_year_end');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn([
                'contact_person_name',
                'website',
                'additional_information',
                'legal_business_name',
                'company_house_number',
                'business_phone_number',
                'number_of_employees',
                'notes',
            ]);
        });
    }
};
