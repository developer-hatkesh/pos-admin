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
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $companyKey = $columnNames['team_foreign_key'] ?? 'company_id';
        $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';

        if (empty($tableNames)) {
            throw new RuntimeException('Permission table names are not configured.');
        }

        if (! Schema::hasColumn($tableNames['roles'], $companyKey)) {
            Schema::table($tableNames['roles'], function (Blueprint $table) use ($companyKey): void {
                $table->unsignedBigInteger($companyKey)->nullable()->after('id');
                $table->index($companyKey, 'roles_company_id_index');
            });

            $this->dropIndexIfExists($tableNames['roles'], 'roles_name_guard_name_unique');

            Schema::table($tableNames['roles'], function (Blueprint $table) use ($companyKey): void {
                $table->unique([$companyKey, 'name', 'guard_name'], 'roles_company_name_guard_unique');
            });
        }

        if (! Schema::hasColumn($tableNames['model_has_permissions'], $companyKey)) {
            Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($companyKey): void {
                $table->unsignedBigInteger($companyKey)->default(1);
                $table->index($companyKey, 'model_has_permissions_company_id_index');
            });

            $this->rebuildModelPermissionPrimary($tableNames['model_has_permissions'], $tableNames['permissions'], $companyKey, $pivotPermission, $columnNames['model_morph_key']);
        }

        if (! Schema::hasColumn($tableNames['model_has_roles'], $companyKey)) {
            Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($companyKey): void {
                $table->unsignedBigInteger($companyKey)->default(1);
                $table->index($companyKey, 'model_has_roles_company_id_index');
            });

            $this->rebuildModelRolePrimary($tableNames['model_has_roles'], $tableNames['roles'], $companyKey, $pivotRole, $columnNames['model_morph_key']);
        }

        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        // Intentionally left as a forward-only data migration. Dropping the team
        // columns would destroy tenant-scoped role assignments.
    }

    private function rebuildModelPermissionPrimary(string $pivotTable, string $permissionsTable, string $companyKey, string $pivotPermission, string $morphKey): void
    {
        Schema::table($pivotTable, function (Blueprint $table) use ($pivotPermission): void {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign([$pivotPermission]);
            }

            $table->dropPrimary();
        });

        Schema::table($pivotTable, function (Blueprint $table) use ($permissionsTable, $companyKey, $pivotPermission, $morphKey): void {
            $table->primary([$companyKey, $pivotPermission, $morphKey, 'model_type'], 'model_has_permissions_permission_model_type_primary');

            if (DB::getDriverName() !== 'sqlite') {
                $table->foreign($pivotPermission)->references('id')->on($permissionsTable)->cascadeOnDelete();
            }
        });
    }

    private function rebuildModelRolePrimary(string $pivotTable, string $rolesTable, string $companyKey, string $pivotRole, string $morphKey): void
    {
        Schema::table($pivotTable, function (Blueprint $table) use ($pivotRole): void {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign([$pivotRole]);
            }

            $table->dropPrimary();
        });

        Schema::table($pivotTable, function (Blueprint $table) use ($rolesTable, $companyKey, $pivotRole, $morphKey): void {
            $table->primary([$companyKey, $pivotRole, $morphKey, 'model_type'], 'model_has_roles_role_model_type_primary');

            if (DB::getDriverName() !== 'sqlite') {
                $table->foreign($pivotRole)->references('id')->on($rolesTable)->cascadeOnDelete();
            }
        });
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        try {
            Schema::table($table, function (Blueprint $table) use ($index): void {
                $table->dropUnique($index);
            });
        } catch (Throwable) {
            //
        }
    }
};
