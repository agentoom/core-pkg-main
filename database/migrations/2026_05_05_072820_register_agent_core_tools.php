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
        $tools = [
            [
                'name' => 'Search Agent Knowledge',
                'class_name' => 'Agentoom\Core\Ai\Tools\Public\SearchAgentKnowledgeTool',
                'description' => 'Search the agent\'s knowledge base (uploaded files and documents).',
                'is_available' => 1,
                'is_public' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Manage Agent Knowledge',
                'class_name' => 'Agentoom\Core\Ai\Tools\Public\ManageAgentKnowledgeTool',
                'description' => 'Add or modify entries in the agent\'s knowledge base.',
                'is_available' => 1,
                'is_public' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Search Agent Learned Skills',
                'class_name' => 'Agentoom\Core\Ai\Tools\Public\SearchAgentLearnedSkillsTool',
                'description' => 'Search for instructions and procedures learned by the agent through experience.',
                'is_available' => 1,
                'is_public' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Manage Agent Learned Skills',
                'class_name' => 'Agentoom\Core\Ai\Tools\Public\ManageAgentLearnedSkillsTool',
                'description' => 'Store and retrieve concise instructions about how to achieve specific tasks learned through experience.',
                'is_available' => 1,
                'is_public' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($tools as $tool) {
            DB::table('tools')->updateOrInsert(
                ['class_name' => $tool['class_name']],
                $tool
            );
        }

        // Update agent_skills namespaces
        $skills = DB::table('agent_skills')->get();
        foreach ($skills as $skill) {
            $toolsArray = json_decode($skill->tools, true);
            if (is_array($toolsArray)) {
                $updated = false;
                foreach ($toolsArray as $key => $toolClass) {
                    if (str_contains($toolClass, 'Agentoom\Core\Ai\Tools\Agent\\')) {
                        $toolsArray[$key] = str_replace('Agentoom\Core\Ai\Tools\Agent\\', 'Agentoom\Core\Ai\Tools\Public\\', $toolClass);
                        $updated = true;
                    }
                }
                if ($updated) {
                    DB::table('agent_skills')->where('id', $skill->id)->update(['tools' => json_encode($toolsArray)]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('tools')->whereIn('class_name', [
            'Agentoom\Core\Ai\Tools\Public\SearchAgentKnowledgeTool',
            'Agentoom\Core\Ai\Tools\Public\ManageAgentKnowledgeTool',
            'Agentoom\Core\Ai\Tools\Public\SearchAgentLearnedSkillsTool',
            'Agentoom\Core\Ai\Tools\Public\ManageAgentLearnedSkillsTool',
        ])->delete();
    }
};
