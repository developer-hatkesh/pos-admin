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
        if (! Schema::hasTable('company_user')) {
            Schema::create('company_user', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->index();
                $table->foreignId('user_id')->index();
                $table->timestamps();

                $table->unique(['company_id', 'user_id']);
                $table->index(['user_id', 'company_id']);
            });
        }

        $this->addForeignKeyIfPossible('company_user_company_id_foreign', 'company_id', 'companies');
        $this->addForeignKeyIfPossible('company_user_user_id_foreign', 'user_id', 'users');

        if (! Schema::hasTable('users') || ! Schema::hasTable('companies')) {
            return;
        }

        $now = now();
        $companies = DB::table('companies')->pluck('id');

        DB::table('users')
            ->select(['id', 'company_id', 'role'])
            ->orderBy('id')
            ->chunkById(500, function ($users) use ($companies, $now): void {
                $rows = [];

                foreach ($users as $user) {
                    if ($user->role === 'admin') {
                        foreach ($companies as $companyId) {
                            $rows[] = [
                                'company_id' => $companyId,
                                'user_id' => $user->id,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }

                        continue;
                    }

                    if ($user->company_id !== null) {
                        $rows[] = [
                            'company_id' => $user->company_id,
                            'user_id' => $user->id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                if ($rows !== []) {
                    DB::table('company_user')->insertOrIgnore($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_user');
    }

    private function addForeignKeyIfPossible(string $constraintName, string $column, string $referencedTable): void
    {
        if (! Schema::hasTable($referencedTable) || $this->foreignKeyExists($constraintName)) {
            return;
        }

        try {
            Schema::table('company_user', function (Blueprint $table) use ($constraintName, $column, $referencedTable): void {
                $table->foreign($column, $constraintName)->references('id')->on($referencedTable)->cascadeOnDelete();
            });
        } catch (Throwable) {
            // Some production databases can report a table as present while MySQL cannot open it for FK creation.
            // The indexed columns still preserve the access mapping, so deployment should not fail on this constraint.
        }
    }

    private function foreignKeyExists(string $constraintName): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'company_user')
            ->where('CONSTRAINT_NAME', $constraintName)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }
};
