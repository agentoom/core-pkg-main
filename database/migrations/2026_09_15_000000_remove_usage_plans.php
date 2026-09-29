<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Usage plans were removed in favour of instance-wide resource caps
        // that come from the AGENTOOM_MAX_* environment variables.
        Schema::dropIfExists('plans');

        DB::table('configurations')->where('key', 'current_plan_id')->delete();

        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $permissionPivot = $columnNames['permission_pivot_key'] ?? 'permission_id';

        $permissionIds = DB::table($tableNames['permissions'])
            ->where('name', 'like', 'plans.%')
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table($tableNames['role_has_permissions'] ?? 'role_has_permissions')
                ->whereIn($permissionPivot, $permissionIds)
                ->delete();
            DB::table($tableNames['model_has_permissions'] ?? 'model_has_permissions')
                ->whereIn($permissionPivot, $permissionIds)
                ->delete();
            DB::table($tableNames['permissions'])
                ->whereIn('id', $permissionIds)
                ->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally irreversible: usage plans no longer exist.
    }
};
