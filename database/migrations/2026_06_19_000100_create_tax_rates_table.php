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
        Schema::create('tax_rates', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->decimal('rate', 5, 2);
            $table->timestamps();
        });

        DB::table('tax_rates')->upsert([
            ['id' => 1, 'name' => 'Standard', 'rate' => 20.00, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Reduced', 'rate' => 5.00, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Zero', 'rate' => 0.00, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'name' => 'Exempt', 'rate' => 0.00, 'created_at' => now(), 'updated_at' => now()],
        ], ['id'], ['name', 'rate', 'updated_at']);
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
