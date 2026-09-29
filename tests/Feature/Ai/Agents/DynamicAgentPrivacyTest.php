<?php

namespace Agentoom\Core\Tests\Feature\Ai\Agents;

use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Contracts\PrivacyIntegration;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DynamicAgentPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_headless_run_respects_agent_anonymization_flag(): void
    {
        $this->mock(PrivacyIntegration::class, function ($mock) {
            $mock->shouldReceive('shouldAnonymizeForAgent')->andReturn(true);
        });

        $agentModel = Agent::factory()->create();

        $agent = new DynamicAgent($agentModel, isHeadless: true);

        $this->assertStringContainsString('## Privacy & Data Anonymization', $agent->instructions());
    }

    public function test_headless_run_skips_privacy_instructions_when_agent_flag_disabled(): void
    {
        $this->mock(PrivacyIntegration::class, function ($mock) {
            $mock->shouldReceive('shouldAnonymizeForAgent')->andReturn(false);
        });

        $agentModel = Agent::factory()->create(['anonymization_enabled' => false]);

        $agent = new DynamicAgent($agentModel, isHeadless: true);

        $this->assertStringNotContainsString('## Privacy & Data Anonymization', $agent->instructions());
    }
}
