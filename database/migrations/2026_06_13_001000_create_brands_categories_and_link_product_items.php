<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        Schema::create('brands', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        Schema::table('product_items', function (Blueprint $table): void {
            $table->foreignId('category_id')->nullable()->after('company_id')->constrained('categories')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->after('category_id')->constrained('brands')->nullOnDelete();
            $table->index(['company_id', 'category_id']);
            $table->index(['company_id', 'brand_id']);
        });
    }

    public function down(): void
    {
        Schema::table('product_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('brand_id');
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }
};
