<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\McpActionable;
use Agentoom\Core\Models\McpServer;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineStep;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PipelineStepInstructionsPrefixTest extends TestCase
{
    use RefreshDatabase;

    private McpServer $server;

    private Agent $agent;

    private McpActionable $actionable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->server = McpServer::create([
            'name' => 'Test Server',
            'url' => 'http://localhost:8080/mcp',
            'transport_type' => 'http',
            'status' => 'active',
        ]);

        $this->agent = Agent::create([
            'mcp_server_id' => $this->server->id,
            'name' => 'Test Agent',
            'slug' => 'test-agent',
            'is_active' => true,
        ]);

        $this->actionable = McpActionable::create([
            'mcp_server_id' => $this->server->id,
            'name' => 'Test Actionable',
            'remote_class' => 'TestClass',
            'remote_method' => 'testMethod',
            'parameters_schema' => [],
        ]);
    }

    public function test_it_adds_prefix_to_plain_text_instructions_for_agent_steps_on_save(): void
    {
        $pipeline = Pipeline::create(['name' => 'Test Pipeline']);
        $step = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'agent_id' => $this->agent->id,
            'order' => 0,
            'instructions_override' => 'Original instructions',
        ]);

        $this->assertStringStartsWith('[STEP NUMBER 1 saved on ', $step->instructions_override);
        $this->assertStringContainsString('Original instructions', $step->instructions_override);
    }

    public function test_it_updates_prefix_on_subsequent_save(): void
    {
        $pipeline = Pipeline::create(['name' => 'Test Pipeline']);
        $step = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'agent_id' => $this->agent->id,
            'order' => 1,
            'instructions_override' => 'Instructions',
        ]);

        $firstInstructions = $step->instructions_override;

        sleep(1); // Ensure datetime changes

        $step->instructions_override = 'Updated instructions';
        $step->save();

        $this->assertNotEquals($firstInstructions, $step->instructions_override);
        $this->assertStringStartsWith('[STEP NUMBER 2 saved on ', $step->instructions_override);
        $this->assertStringContainsString('Updated instructions', $step->instructions_override);
        // Ensure we don't have double prefix
        $this->assertEquals(1, substr_count($step->instructions_override, '[STEP NUMBER 2 saved on '));
    }

    public function test_it_does_not_add_prefix_if_instructions_are_empty(): void
    {
        $pipeline = Pipeline::create(['name' => 'Test Pipeline']);
        $step = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 0,
            'instructions_override' => null,
        ]);

        $this->assertNull($step->instructions_override);

        $step->instructions_override = '';
        $step->save();
        $this->assertEquals('', $step->instructions_override);
    }

    public function test_it_adds_cache_buster_to_json_instructions_for_agent_steps(): void
    {
        $pipeline = Pipeline::create(['name' => 'Test Pipeline']);
        $json = json_encode([['name' => 'param1', 'value' => 'val1']]);
        $step = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'agent_id' => $this->agent->id,
            'order' => 0,
            'instructions_override' => $json,
        ]);

        $decoded = json_decode($step->instructions_override, true);
        $this->assertIsArray($decoded);
        $this->assertNotEmpty($decoded);
        $this->assertEquals('_cache_buster', $decoded[0]['name']);
        $this->assertStringStartsWith('[STEP NUMBER 1 saved on ', $decoded[0]['value']);
        $this->assertEquals('param1', $decoded[1]['name']);
    }

    public function test_it_does_not_add_prefix_to_actionable_step_json_instructions(): void
    {
        $pipeline = Pipeline::create(['name' => 'Test Pipeline']);
        $json = json_encode([['name' => 'url', 'value' => 'https://example.com']]);
        $step = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'mcp_actionable_id' => $this->actionable->id,
            'order' => 0,
            'instructions_override' => $json,
        ]);

        $decoded = json_decode($step->instructions_override, true);
        $this->assertIsArray($decoded);
        $this->assertCount(1, $decoded);
        $this->assertEquals('url', $decoded[0]['name']);
        $this->assertEquals('https://example.com', $decoded[0]['value']);
    }

    public function test_it_does_not_add_prefix_to_pass_through_step_json_instructions(): void
    {
        $pipeline = Pipeline::create(['name' => 'Test Pipeline']);
        $json = json_encode([['name' => 'agentId', 'type' => 'integer', 'value' => 1]]);
        $step = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 0,
            'instructions_override' => $json,
        ]);

        $decoded = json_decode($step->instructions_override, true);
        $this->assertIsArray($decoded);
        $this->assertCount(1, $decoded);
        $this->assertEquals('agentId', $decoded[0]['name']);
    }
}
