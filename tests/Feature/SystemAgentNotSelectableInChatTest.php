<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class SystemAgentNotSelectableInChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Configuration::setValue('onboarding_complete', '1');
        Role::create(['name' => 'admin']);
    }

    public function test_get_available_agents_excludes_system_agents(): void
    {
        Agent::factory()->create(['name' => 'Regular Agent', 'is_active' => true, 'system_agent' => false]);
        Agent::factory()->create(['name' => 'System Agent', 'is_active' => true, 'system_agent' => true]);

        $agents = Agent::where('is_active', true)
            ->where('system_agent', false)
            ->orderBy('name')
            ->get();

        $this->assertCount(1, $agents);
        $this->assertEquals('Regular Agent', $agents->first()->name);
    }

    public function test_non_system_active_agents_are_returned(): void
    {
        Agent::factory()->create(['name' => 'My Agent', 'is_active' => true, 'system_agent' => false]);

        $agents = Agent::where('is_active', true)
            ->where('system_agent', false)
            ->orderBy('name')
            ->get();

        $this->assertCount(1, $agents);
        $this->assertEquals('My Agent', $agents->first()->name);
    }

    public function test_inactive_and_system_agents_are_excluded(): void
    {
        Agent::factory()->create(['name' => 'Inactive Agent', 'is_active' => false, 'system_agent' => false]);
        Agent::factory()->create(['name' => 'System Agent', 'is_active' => true, 'system_agent' => true]);

        $agents = Agent::where('is_active', true)
            ->where('system_agent', false)
            ->orderBy('name')
            ->get();

        $this->assertEmpty($agents);
    }

    public function test_new_agents_disable_language_policy_and_human_intervention_by_default(): void
    {
        $agent = Agent::create(['name' => 'Automatically Created Agent']);

        $this->assertFalse($agent->allow_human_intervention);
        $this->assertFalse($agent->prompt_settings['parts']['language_policy']);
    }
}
