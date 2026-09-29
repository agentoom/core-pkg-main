<?php

namespace Agentoom\Core\Tests\Feature\Ai\Tools;

use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Ai\Tools\ReadSkillTool;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AgentSkill;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

class ReadSkillToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_assigned_skills()
    {
        $agent = Agent::factory()->create(['system_agent' => false]);
        $skill = AgentSkill::factory()->create([
            'name' => 'Test Skill',
            'description' => 'Test Description',
        ]);
        $agent->skills()->attach($skill);

        $tool = new ReadSkillTool($agent);
        $result = $tool->handle(new Request(['action' => 'list_skills']));

        $this->assertStringContainsString('Your assigned skills:', $result);
        $this->assertStringContainsString('Test Skill', $result);
        $this->assertStringContainsString('Test Description', $result);
        $this->assertStringContainsString((string) $skill->id, $result);
    }

    public function test_it_reads_assigned_skill_content()
    {
        $agent = Agent::factory()->create(['system_agent' => false]);
        $skill = AgentSkill::factory()->create([
            'name' => 'Detailed Skill',
            'instructions' => 'Follow these steps.',
            'musts' => ['Do this'],
            'donts' => ['Dont do that'],
        ]);
        $agent->skills()->attach($skill);

        $tool = new ReadSkillTool($agent);
        $result = $tool->handle(new Request([
            'action' => 'get_skill',
            'skill_id' => $skill->id,
        ]));

        $this->assertStringContainsString('Detailed Skill', $result);
        $this->assertStringContainsString('Follow these steps.', $result);
        $this->assertStringContainsString('REQUIRED ACTIONS (MUSTS):', $result);
        $this->assertStringContainsString('Do this', $result);
        $this->assertStringContainsString('PROHIBITED ACTIONS (DON\'TS):', $result);
        $this->assertStringContainsString('Dont do that', $result);
    }

    public function test_it_denies_access_to_unassigned_skill()
    {
        $agent = Agent::factory()->create(['system_agent' => false]);
        $skill = AgentSkill::factory()->create(['name' => 'Secret Skill']);
        // Not attached

        $tool = new ReadSkillTool($agent);
        $result = $tool->handle(new Request([
            'action' => 'get_skill',
            'skill_id' => $skill->id,
        ]));

        $this->assertStringContainsString('Error: The requested skill does not exist or is not assigned to you.', $result);
        $this->assertStringNotContainsString('Secret Skill', $result);
    }

    public function test_dynamic_agent_prompt_only_lists_skills()
    {
        $agent = Agent::factory()->create();
        $skill = AgentSkill::factory()->create([
            'name' => 'Skill Name',
            'description' => 'Skill Description',
            'instructions' => 'Detailed Secret Instructions',
        ]);
        $agent->skills()->attach($skill);

        $dynamicAgent = new DynamicAgent($agent);
        $instructions = $dynamicAgent->instructions();

        $this->assertStringContainsString('Skill Name', $instructions);
        $this->assertStringContainsString('Skill Description', $instructions);
        $this->assertStringContainsString('ReadSkill', $instructions);
        $this->assertStringContainsString('IMPORTANT: You should ONLY use the \'ReadSkill\' tool', $instructions);
        $this->assertStringNotContainsString('Detailed Secret Instructions', $instructions);
    }

    public function test_dynamic_agent_provides_read_skill_tool_to_non_system_agents()
    {
        $agent = Agent::factory()->create(['system_agent' => false]);
        $dynamicAgent = new DynamicAgent($agent);

        $toolNames = collect($dynamicAgent->tools())->map(fn ($t) => method_exists($t, 'name') ? $t->name() : class_basename($t))->toArray();

        $this->assertContains('ReadSkill', $toolNames);
    }

    public function test_dynamic_agent_does_not_provide_read_skill_tool_to_system_agents()
    {
        $agent = Agent::factory()->create(['system_agent' => true]);
        $dynamicAgent = new DynamicAgent($agent);

        $toolNames = collect($dynamicAgent->tools())->map(fn ($t) => method_exists($t, 'name') ? $t->name() : class_basename($t))->toArray();

        $this->assertNotContains('ReadSkill', $toolNames);
    }

    public function test_it_has_correct_description()
    {
        $agent = Agent::factory()->create();
        $tool = new ReadSkillTool($agent);
        $this->assertStringContainsString('Recall detailed instructions', $tool->description());
        $this->assertStringContainsString('only when it is directly relevant', $tool->description());
    }
}
