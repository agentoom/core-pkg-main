<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, change the column type to avoid JSON validation errors on PostgreSQL
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE resource_groups ALTER COLUMN name TYPE VARCHAR(255) USING name::text');
        } else {
            Schema::table('resource_groups', function (Blueprint $table) {
                $table->string('name')->change();
            });
        }

        // Now, extract the names from the JSON strings
        $groups = DB::table('resource_groups')->get();

        foreach ($groups as $group) {
            $name = json_decode($group->name, true);
            $newName = null;

            if (is_array($name)) {
                $newName = $name['en'] ?? reset($name) ?: 'Untitled';
            }

            if ($newName !== null) {
                DB::table('resource_groups')->where('id', $group->id)->update(['name' => $newName]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First, convert the names back to JSON strings while the column is still VARCHAR
        $groups = DB::table('resource_groups')->get();

        foreach ($groups as $group) {
            DB::table('resource_groups')->where('id', $group->id)->update([
                'name' => json_encode(['en' => $group->name]),
            ]);
        }

        // Now, change the column type back to JSON
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE resource_groups ALTER COLUMN name TYPE JSON USING name::json');
        } else {
            Schema::table('resource_groups', function (Blueprint $table) {
                $table->json('name')->change();
            });
        }
    }
};
