<?php

namespace Agentoom\Core\Ai\Tools;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Traits\HandlesToolActions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ReadSkillTool implements Tool
{
    use HandlesToolActions;

    public function __construct(protected Agent $agent) {}

    /**
     * Get the tool's name.
     */
    public function name(): string
    {
        return 'ReadSkill';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Recall detailed instructions for a specific skill only when it is directly relevant to the current task.';
    }

    /**
     * List all skills assigned to the agent.
     */
    protected function list_skills(Request $request): string
    {
        $skills = $this->agent->skills()->get(['agent_skills.id', 'name', 'description']);

        if ($skills->isEmpty()) {
            return 'You have no skills assigned.';
        }

        $data = $skills->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'description' => $s->description,
        ])->toArray();

        return "Your assigned skills:\n".json_encode($data, JSON_PRETTY_PRINT);
    }

    /**
     * Get the detailed content of a specific skill.
     */
    protected function get_skill(Request $request): string
    {
        $skillId = $request->integer('skill_id');

        if (! $skillId) {
            return 'Error: skill_id is required.';
        }

        $skill = $this->agent->skills()->find($skillId);

        if (! $skill) {
            return 'Error: The requested skill does not exist or is not assigned to you.';
        }

        $s = "## Skill: {$skill->name}\n";
        $s .= "{$skill->instructions}\n";

        if (! empty($skill->musts)) {
            $s .= "\n### REQUIRED ACTIONS (MUSTS):\n- ".implode("\n- ", (array) $skill->musts);
        }

        if (! empty($skill->donts)) {
            $s .= "\n### PROHIBITED ACTIONS (DON'TS):\n- ".implode("\n- ", (array) $skill->donts);
        }

        if (! empty($skill->if_this_then)) {
            $s .= "\n### PROCEDURAL LOGIC:\n";
            foreach ($skill->if_this_then as $logic) {
                $condition = $logic['event'] ?? 'unknown condition';
                $action = $logic['action'] ?? 'unknown action';
                $s .= "- IF: {$condition} -> THEN: {$action}\n";
            }
        }

        return $s;
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['list_skills', 'get_skill'])
                ->required()
                ->description('The action to perform: "list_skills" to see available skills, or "get_skill" to read the content of a specific skill.'),
            'skill_id' => $schema->integer()
                ->description('The ID of the skill to read (REQUIRED for "get_skill" action).'),
        ];
    }
}
