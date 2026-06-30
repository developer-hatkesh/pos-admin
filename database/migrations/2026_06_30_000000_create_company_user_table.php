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
        if (! Schema::hasTable('users') || ! Schema::hasTable('companies')) {
            return;
        }

        if (! Schema::hasTable('company_user')) {
            Schema::create('company_user', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['company_id', 'user_id']);
                $table->index(['user_id', 'company_id']);
            });
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
};
