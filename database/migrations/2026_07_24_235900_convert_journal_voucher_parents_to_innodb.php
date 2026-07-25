<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (['users', 'journal_entries', 'sales_returns'] as $table) {
            if (! Schema::hasTable($table) || $this->engineFor($table) === 'InnoDB') {
                continue;
            }

            DB::statement("ALTER TABLE `{$table}` ENGINE = InnoDB");
        }
    }

    public function down(): void
    {
        // Engine conversions are intentionally not reversed to MyISAM.
    }

    private function engineFor(string $table): ?string
    {
        $result = DB::selectOne(
            'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table],
        );

        return $result?->ENGINE;
    }
};
