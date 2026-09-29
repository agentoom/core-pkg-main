<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\McpServer;
use Agentoom\Core\Services\McpDiscoveryService;
use Agentoom\Core\Services\McpService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class McpSyncReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_accurate_counts_and_does_not_update_existing()
    {
        $server = McpServer::create([
            'name' => 'Test Server',
            'url' => 'http://localhost:8080/mcp',
            'transport_type' => 'http',
            'status' => 'active',
        ]);

        // 1. Pre-existing element belonging to this server
        $existingAgent = Agent::create([
            'name' => 'Existing Agent',
            'description' => 'Original Description',
            'mcp_server_id' => $server->id,
            'remote_name' => 'Existing Agent',
        ]);

        // 2. Two skills with same slug in the same sync will cause one to be skipped
        $mockMcpService = Mockery::mock(McpService::class);
        $mockMcpService->shouldReceive('listTools')->andReturn(['tools' => []]);
        $mockMcpService->shouldReceive('listResources')->andReturn(['resources' => []]);
        $mockMcpService->shouldReceive('getActionables')->andReturn([]);
        $mockMcpService->shouldReceive('getAgentoomPrivacyRecognizers')->andReturn([]);
        $mockMcpService->shouldReceive('getAgentoomElements')
            ->andReturn([
                'agents' => [
                    [
                        'name' => 'Existing Agent',
                        'description' => 'Updated Description', // Should NOT be updated
                    ],
                    [
                        'name' => 'New Agent',
                        'description' => 'New Agent Description',
                    ],
                ],
                'skills' => [
                    [
                        'name' => 'First Skill',
                        'slug' => 'conflict',
                        'description' => 'Description',
                    ],
                    [
                        'name' => 'Second Skill', // Same slug, should be skipped
                        'slug' => 'conflict',
                        'description' => 'Description',
                    ],
                    [
                        'name' => 'New Skill',
                        'description' => 'New Skill Description',
                    ],
                ],
            ]);

        $this->app->instance(McpService::class, $mockMcpService);

        $discoveryService = app(McpDiscoveryService::class);

        // Add an agent that will be deleted
        Agent::create([
            'name' => 'To Be Deleted',
            'mcp_server_id' => $server->id,
            'remote_name' => 'To Be Deleted',
        ]);

        $results = $discoveryService->syncServer($server);

        // Verify Agents report
        $this->assertEquals(1, $results['agents']['imported']);
        $this->assertEquals(1, $results['agents']['existing']);
        $this->assertEquals(1, $results['agents']['deleted']);
        $this->assertEmpty($results['agents']['skipped']);

        // Verify Skills report
        $this->assertEquals(2, $results['skills']['imported']);
        $this->assertEquals(0, $results['skills']['existing']);
        $this->assertCount(1, $results['skills']['skipped']);
        $this->assertStringContainsString('Second Skill', $results['skills']['skipped'][0]);
        $this->assertStringContainsString('slug conflict', $results['skills']['skipped'][0]);

        // Verify NO update occurred for existing agent
        $existingAgent->refresh();
        $this->assertEquals('Original Description', $existingAgent->description);

        // Verify database state
        $this->assertDatabaseHas('agents', ['name' => 'New Agent']);
        $this->assertDatabaseHas('agent_skills', ['name' => 'New Skill']);
    }
}
