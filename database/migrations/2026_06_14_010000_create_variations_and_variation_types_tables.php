<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('variations')) {
            Schema::create('variations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id');
                $table->string('name');
                $table->timestamps();

                $table->unique(['company_id', 'name']);
            });
        }

        if (
            Schema::hasTable('companies')
            && Schema::hasColumn('variations', 'company_id')
            && ! $this->foreignKeyExists('variations', 'variations_company_id_foreign')
        ) {
            try {
                Schema::table('variations', function (Blueprint $table): void {
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('companies')
                        ->cascadeOnDelete();
                });
            } catch (QueryException $exception) {
                if (! str_contains($exception->getMessage(), 'Failed to open the referenced table')) {
                    throw $exception;
                }
            }
        }

        if (! Schema::hasTable('variation_types')) {
            Schema::create('variation_types', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('variation_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->timestamps();

                $table->unique(['variation_id', 'name']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('variation_types');
        Schema::dropIfExists('variations');
    }

    private function foreignKeyExists(string $table, string $foreignKeyName): bool
    {
        return collect(Schema::getForeignKeys($table))
            ->contains(fn (array $foreignKey): bool => ($foreignKey['name'] ?? null) === $foreignKeyName);
    }
};
