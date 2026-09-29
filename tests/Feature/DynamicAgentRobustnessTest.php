<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AgentSkill;
use Agentoom\Core\Models\Tool;
use Agentoom\Core\Models\UserInformation;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DynamicAgentRobustnessTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_handles_array_values_in_instructions_generation()
    {
        $agent = Agent::factory()->create([
            'prioritize' => [['nested' => 'array']],
            'constraints' => [['another' => 'array']],
            'output_format' => [['format' => 'array']],
            'conditional_rules' => [
                ['condition' => ['some' => 'condition'], 'if_true' => ['do' => 'this'], 'if_false' => ['else' => 'that']],
            ],
            'examples' => [
                ['input' => ['nested' => 'input'], 'output' => ['nested' => 'output']],
            ],
        ]);

        $skill = AgentSkill::factory()->create([
            'musts' => [['must' => 'be nested']],
            'donts' => [['dont' => 'be nested']],
        ]);

        $agent->skills()->attach($skill);

        $dynamicAgent = new DynamicAgent($agent);

        // This should not throw "Array to string conversion"
        $instructions = $dynamicAgent->instructions();

        $this->assertStringContainsString('{"nested":"array"}', $instructions);
        $this->assertStringContainsString('{"another":"array"}', $instructions);
        $this->assertStringContainsString('{"format":"array"}', $instructions);
        $this->assertStringContainsString('IF {"some":"condition"} THEN {"do":"this"} OTHERWISE {"else":"that"}', $instructions);
        $this->assertStringContainsString('Input: {"nested":"input"}', $instructions);
        $this->assertStringContainsString('Output: {"nested":"output"}', $instructions);
        $this->assertStringContainsString('REQUIRED ACTIONS (MUSTS):', $instructions);
        $this->assertStringContainsString('{"must":"be nested"}', $instructions);
        $this->assertStringContainsString('PROHIBITED ACTIONS (DON\'TS):', $instructions);
        $this->assertStringContainsString('{"dont":"be nested"}', $instructions);
    }

    public function test_language_policy_is_only_added_when_explicitly_enabled(): void
    {
        $disabledAgent = Agent::factory()->create([
            'prompt_settings' => ['parts' => ['language_policy' => false]],
        ]);
        $missingPolicyAgent = Agent::factory()->create([
            'prompt_settings' => ['parts' => []],
        ]);
        $enabledAgent = Agent::factory()->create([
            'prompt_settings' => ['parts' => ['language_policy' => true]],
        ]);

        $this->assertStringNotContainsString('## Language Policy', (new DynamicAgent($disabledAgent))->instructions());
        $this->assertStringNotContainsString('## Language Policy', (new DynamicAgent($missingPolicyAgent))->instructions());
        $this->assertStringContainsString('## Language Policy', (new DynamicAgent($enabledAgent))->instructions());
    }

    public function test_live_chat_includes_only_important_active_memories(): void
    {
        $user = User::factory()->create();
        $agent = Agent::factory()->create([
            'prompt_settings' => ['parts' => ['user_context' => true]],
        ]);
        $tool = Tool::create([
            'name' => 'Search User Information',
            'class_name' => 'Agentoom\\Core\\Ai\\Tools\\Public\\SearchUserInformationTool',
            'description' => 'Search user information.',
        ]);
        $agent->tools()->attach($tool);
        UserInformation::create([
            'user_id' => $user->id,
            'title' => 'preferred_name',
            'content' => 'Giovannone',
            'importance' => 5,
        ]);
        UserInformation::create([
            'user_id' => $user->id,
            'title' => 'low_priority_detail',
            'content' => 'Do not inject this automatically.',
            'importance' => 2,
        ]);
        UserInformation::create([
            'user_id' => $user->id,
            'title' => 'expired_detail',
            'content' => 'This is no longer valid.',
            'importance' => 5,
            'expires_at' => now()->subMinute(),
        ]);

        $instructions = (new DynamicAgent($agent, user: $user))->instructions();

        $this->assertStringContainsString('preferred_name: Giovannone', $instructions);
        $this->assertStringNotContainsString('low_priority_detail', $instructions);
        $this->assertStringNotContainsString('expired_detail', $instructions);
    }
}
