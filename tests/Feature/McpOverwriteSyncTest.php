<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\McpServer;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Models\Tool;
use Agentoom\Core\Services\McpDiscoveryService;
use Agentoom\Core\Services\McpService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class McpOverwriteSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_not_overwrite_by_default()
    {
        $server = McpServer::create([
            'name' => 'Test Server',
            'url' => 'http://localhost:8080/mcp',
            'transport_type' => 'http',
            'status' => 'active',
        ]);

        $existingAgent = Agent::create([
            'name' => 'Existing Agent',
            'description' => 'Original Description',
            'mcp_server_id' => $server->id,
            'remote_name' => 'Existing Agent',
        ]);

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
                        'description' => 'Updated Description',
                    ],
                ],
                'skills' => [],
            ]);

        $this->app->instance(McpService::class, $mockMcpService);

        $discoveryService = app(McpDiscoveryService::class);
        $results = $discoveryService->syncServer($server);

        $existingAgent->refresh();
        $this->assertEquals('Original Description', $existingAgent->description);
        $this->assertEquals(1, $results['agents']['existing']);
        $this->assertEquals(0, $results['agents']['imported']);
    }

    public function test_it_overwrites_when_requested()
    {
        $server = McpServer::create([
            'name' => 'Test Server',
            'url' => 'http://localhost:8080/mcp',
            'transport_type' => 'http',
            'status' => 'active',
        ]);

        $existingAgent = Agent::create([
            'name' => 'Existing Agent',
            'description' => 'Original Description',
            'mcp_server_id' => $server->id,
            'remote_name' => 'Existing Agent',
        ]);

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
                        'description' => 'Updated Description',
                    ],
                ],
                'skills' => [],
            ]);

        $this->app->instance(McpService::class, $mockMcpService);

        $discoveryService = app(McpDiscoveryService::class);

        // syncServer(server, overwrite: true)
        $results = $discoveryService->syncServer($server, true);

        $existingAgent->refresh();
        $this->assertEquals('Updated Description', $existingAgent->description);

        $this->assertEquals(1, $results['agents']['updated']);
        $this->assertEquals(0, $results['agents']['existing']);
        $this->assertEquals(0, $results['agents']['imported']);
    }

    public function test_it_overwrites_tools_and_resources()
    {
        $server = McpServer::create([
            'name' => 'Test Server',
            'url' => 'http://localhost:8080/mcp',
            'transport_type' => 'http',
            'status' => 'active',
        ]);

        $remoteName = 'Search Tool';
        $safeRemoteName = preg_replace('/[^a-zA-Z0-9]/', '_', $remoteName);
        $hash = substr(md5($remoteName), 0, 8);
        $className = "McpTool_{$server->id}_{$safeRemoteName}_{$hash}";
        $fullClassName = "Agentoom\Core\Ai\Tools\Dynamic\\{$className}";

        Tool::create([
            'name' => "{$server->name}: {$remoteName}",
            'class_name' => $fullClassName,
            'description' => 'Original Description',
            'mcp_server_id' => $server->id,
            'remote_name' => $remoteName,
            'is_db_backed' => true,
        ]);

        \Agentoom\Core\Models\Resource::create([
            'name' => 'Original Resource',
            'description' => 'Original Description',
            'mcp_server_id' => $server->id,
            'mcp_uri' => 'mcp://test',
            'resource_type_id' => ResourceType::create([
                'name' => 'Test',
                'config' => [],
            ])->id,
        ]);

        $mockMcpService = Mockery::mock(McpService::class);
        $mockMcpService->shouldReceive('listTools')->andReturn([
            'tools' => [
                [
                    'name' => 'Search Tool',
                    'description' => 'Updated Description',
                ],
            ],
        ]);
        $mockMcpService->shouldReceive('listResources')->andReturn([
            'resources' => [
                [
                    'uri' => 'mcp://test',
                    'name' => 'Updated Resource',
                    'description' => 'Updated Description',
                ],
            ],
        ]);
        $mockMcpService->shouldReceive('getActionables')->andReturn([]);
        $mockMcpService->shouldReceive('getAgentoomPrivacyRecognizers')->andReturn([]);
        $mockMcpService->shouldReceive('getAgentoomElements')->andReturn(['agents' => [], 'skills' => []]);

        $this->app->instance(McpService::class, $mockMcpService);

        $discoveryService = app(McpDiscoveryService::class);
        $results = $discoveryService->syncServer($server, true);

        $this->assertDatabaseHas('tools', [
            'class_name' => $fullClassName,
            'description' => 'Updated Description',
        ]);

        $this->assertDatabaseHas('resources', [
            'mcp_uri' => 'mcp://test',
            'name' => 'Updated Resource',
            'description' => 'Updated Description',
        ]);

        $this->assertEquals(1, $results['tools']['updated']);
        $this->assertEquals(1, $results['resources']['updated']);
    }
}
