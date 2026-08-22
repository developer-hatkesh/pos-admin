<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, string> */
    private array $tables = [
        'sales_invoice_items' => 'invoice_id',
        'purchase_invoice_items' => 'invoice_id',
        'sales_return_items' => 'sales_return_id',
        'purchase_return_items' => 'purchase_return_id',
        'estimate_items' => 'estimate_id',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table => $parentColumn) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'sort_order')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->unsignedInteger('sort_order')->default(0)->index();
            });

            $positions = [];
            DB::table($table)
                ->select(['id', $parentColumn])
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($table, $parentColumn, &$positions): void {
                    foreach ($rows as $row) {
                        $parentId = (int) $row->{$parentColumn};
                        $sortOrder = $positions[$parentId] ?? 0;

                        DB::table($table)->where('id', $row->id)->update(['sort_order' => $sortOrder]);
                        $positions[$parentId] = $sortOrder + 1;
                    }
                });
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->tables) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'sort_order')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('sort_order');
            });
        }
    }
};
