<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Tools\Public\ManageAgentLearnedSkillsTool;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AgentLearnedSkill;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

class ManageAgentLearnedSkillsToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_learned_skills()
    {
        $user = User::factory()->create();
        $agent = Agent::factory()->create(['user_id' => $user->id]);

        $tool = new ManageAgentLearnedSkillsTool($agent);
        $request = new Request([
            'action' => 'store',
            'title' => 'WooCommerce API Fix',
            'content' => 'Always pass consumer_secret explicitly.',
        ]);

        $response = $tool->handle($request);

        $this->assertStringContainsString("Successfully stored learned skill 'WooCommerce API Fix'", (string) $response);
        $this->assertDatabaseHas('agent_learned_skills', [
            'agent_id' => $agent->id,
            'title' => 'WooCommerce API Fix',
            'content' => 'Always pass consumer_secret explicitly.',
        ]);
    }

    public function test_it_retrieves_learned_skills()
    {
        $user = User::factory()->create();
        $agent = Agent::factory()->create(['user_id' => $user->id]);

        AgentLearnedSkill::create([
            'agent_id' => $agent->id,
            'title' => 'How to browse',
            'content' => 'Use Google for current events.',
        ]);

        $tool = new ManageAgentLearnedSkillsTool($agent);
        $request = new Request([
            'action' => 'retrieve',
        ]);

        $response = $tool->handle($request);

        $this->assertStringContainsString('### Learned Skill: How to browse', (string) $response);
        $this->assertStringContainsString('Use Google for current events.', (string) $response);
    }
}
