<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_classes', function (Blueprint $table): void {
            $table->id('account_class_id');
            $table->string('account_class_code')->unique();
            $table->string('account_class_name');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('account_categories', function (Blueprint $table): void {
            $table->id('account_category_id');
            $table->foreignId('account_class_id')
                ->constrained('account_classes', 'account_class_id')
                ->cascadeOnDelete();
            $table->string('account_category_code')->unique();
            $table->string('account_category_name');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_categories');
        Schema::dropIfExists('account_classes');
    }
};
