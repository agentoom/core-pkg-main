<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create default resource group
        $generalGroupId = DB::table('resource_groups')->insertGetId([
            'name' => json_encode(['en' => 'General', 'it' => 'Generale']),
            'icon' => 'cube',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Assign existing resource types to General group if they don't have one
        DB::table('resource_types')->whereNull('resource_group_id')->update(['resource_group_id' => $generalGroupId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
