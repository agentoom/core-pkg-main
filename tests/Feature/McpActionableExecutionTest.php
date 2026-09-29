<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\McpActionable;
use Agentoom\Core\Models\McpServer;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineStep;
use Agentoom\Core\Services\McpActionableService;
use Agentoom\Core\Services\PipelineRunnerService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class McpActionableExecutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_execute_remote_actionable_in_pipeline()
    {
        $server = McpServer::create([
            'name' => 'Test Server',
            'url' => 'http://localhost:8080/mcp',
            'transport_type' => 'http',
            'status' => 'active',
        ]);

        $actionable = McpActionable::create([
            'mcp_server_id' => $server->id,
            'name' => 'Fetch URL',
            'remote_class' => 'WebBrowser',
            'remote_method' => 'fetch',
            'parameters_schema' => [
                ['name' => 'url', 'type' => 'string', 'required' => true],
            ],
        ]);

        $pipeline = Pipeline::create([
            'name' => 'Test Pipeline',
            'type' => 'standard',
        ]);

        $step = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'mcp_actionable_id' => $actionable->id,
            'order' => 0,
            'instructions_override' => json_encode([
                ['name' => 'url', 'value' => 'https://example.com'],
            ]),
        ]);

        $mockMcpActionableService = Mockery::mock(McpActionableService::class);
        $mockMcpActionableService->shouldReceive('execute')
            ->with(
                Mockery::on(fn ($a) => $a->id === $actionable->id),
                Mockery::on(function ($args) {
                    // Verify the expected parameter is present
                    if (! isset($args['url']) || $args['url'] !== 'https://example.com') {
                        return false;
                    }
                    // Verify _cache_buster is NOT leaked into actionable params
                    if (isset($args['_cache_buster'])) {
                        return false;
                    }

                    return true;
                })
            )
            ->once()
            ->andReturn('Remote Content');

        $this->app->instance(McpActionableService::class, $mockMcpActionableService);

        $runner = app(PipelineRunnerService::class);
        $run = $runner->run($pipeline);

        $this->assertEquals('completed', $run->status);
        $this->assertEquals('Remote Content', $run->output);

        $this->assertDatabaseHas('pipeline_run_steps', [
            'pipeline_run_id' => $run->id,
            'pipeline_step_id' => $step->id,
            'status' => 'completed',
            'output' => 'Remote Content',
        ]);
    }
}
