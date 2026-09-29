<?php

use Agentoom\Core\Models\AgentSkill;
use Agentoom\Core\Models\Tool;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // The Uduny integration was removed entirely: purge its seeded skill,
        // registered AI tool and stored credentials.
        AgentSkill::where('slug', 'uduny-search')->delete();

        Tool::where('class_name', 'Agentoom\Core\Ai\Tools\Public\UdunyTool')
            ->orWhere('name', 'Uduny Tool')
            ->get()
            ->each(fn (Tool $tool) => $tool->delete());

        DB::table('configurations')->where('key', 'uduny_integration')->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally irreversible: the Uduny integration no longer exists.
    }
};
