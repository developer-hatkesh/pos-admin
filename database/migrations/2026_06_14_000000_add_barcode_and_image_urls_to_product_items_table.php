<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_items', function (Blueprint $table): void {
            $table->string('barcode')->nullable()->after('item_code');
            $table->json('image_urls')->nullable()->after('opening_stock');
        });
    }

    public function down(): void
    {
        Schema::table('product_items', function (Blueprint $table): void {
            $table->dropColumn(['barcode', 'image_urls']);
        });
    }
};
