<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Endpoint;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Ai;

class EndpointPromptTest extends TestCase
{
    use RefreshDatabase;

    public function test_endpoint_passes_prompt_to_agent(): void
    {
        Ai::fakeAgent(DynamicAgent::class, function (string $prompt) {
            $this->assertTrue(str_contains($prompt, 'Custom instructions for the agent.'));
            $this->assertTrue(str_contains($prompt, 'Processed data from endpoint \'Prompt Test Endpoint\''));

            return 'task successful';
        });

        $agentModel = Agent::factory()->create(['is_active' => true]);

        $endpoint = Endpoint::create([
            'agent_id' => $agentModel->id,
            'name' => 'Prompt Test Endpoint',
            'slug' => 'prompt-test',
            'prompt' => 'Custom instructions for the agent.',
            'is_active' => true,
        ]);

        $this->postJson('/e/prompt-test', ['foo' => 'bar'])
            ->assertStatus(200);
    }

    public function test_endpoint_works_without_prompt(): void
    {
        Ai::fakeAgent(DynamicAgent::class, function (string $prompt) {
            $this->assertStringNotContainsString('Additional instructions:', $prompt);
            $this->assertTrue(str_contains($prompt, 'Processed data from endpoint \'No Prompt Endpoint\''));

            return 'task successful';
        });

        $agentModel = Agent::factory()->create(['is_active' => true]);

        $endpoint = Endpoint::create([
            'agent_id' => $agentModel->id,
            'name' => 'No Prompt Endpoint',
            'slug' => 'no-prompt',
            'is_active' => true,
        ]);

        $this->postJson('/e/no-prompt', ['foo' => 'bar'])
            ->assertStatus(200);
    }
}
