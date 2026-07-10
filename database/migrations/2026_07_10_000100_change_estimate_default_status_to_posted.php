<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('estimates')) {
            Schema::table('estimates', function (Blueprint $table): void {
                $table->string('status')->default('posted')->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('estimates')) {
            Schema::table('estimates', function (Blueprint $table): void {
                $table->string('status')->default('draft')->change();
            });
        }
    }
};
