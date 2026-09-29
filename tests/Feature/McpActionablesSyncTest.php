<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\McpActionable;
use Agentoom\Core\Models\McpServer;
use Agentoom\Core\Services\McpDiscoveryService;
use Agentoom\Core\Services\McpService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class McpActionablesSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_sync_actionables()
    {
        $server = McpServer::create([
            'name' => 'Test Server',
            'url' => 'http://localhost:8080/mcp',
            'transport_type' => 'http',
            'status' => 'active',
        ]);

        $mockMcpService = Mockery::mock(McpService::class);
        $mockMcpService->shouldReceive('listTools')->andReturn([]);
        $mockMcpService->shouldReceive('listResources')->andReturn([]);
        $mockMcpService->shouldReceive('getAgentoomPrivacyRecognizers')->andReturn([]);
        $mockMcpService->shouldReceive('getAgentoomElements')->andReturn([]);
        $mockMcpService->shouldReceive('getActionables')
            ->andReturn([
                [
                    'name' => 'Fetch URL',
                    'description' => 'Fetch a URL',
                    'category' => 'Browsing',
                    'class' => 'Agentoom\\Seo\\Services\\WebBrowserService',
                    'method' => 'fetch',
                    'parameters' => [
                        ['name' => 'url', 'type' => 'string', 'required' => true],
                    ],
                ],
            ]);

        $this->app->instance(McpService::class, $mockMcpService);

        $discoveryService = app(McpDiscoveryService::class);
        $discoveryService->syncMcpServers();

        $this->assertDatabaseHas('mcp_actionables', [
            'name' => 'Fetch URL',
            'mcp_server_id' => $server->id,
            'remote_class' => 'Agentoom\\Seo\\Services\\WebBrowserService',
            'remote_method' => 'fetch',
        ]);

        $actionable = McpActionable::first();
        $this->assertEquals('Browsing', $actionable->category);
        $this->assertEquals($server->url, $actionable->remote_base_url);
        $this->assertIsArray($actionable->parameters_schema);
        $this->assertEquals('url', $actionable->parameters_schema[0]['name']);
    }

    public function test_it_handles_missing_elements_and_actionables_gracefully()
    {
        $server = McpServer::create([
            'name' => 'Test Server',
            'url' => 'http://localhost:8080/mcp',
            'transport_type' => 'http',
            'status' => 'active',
        ]);

        // Real McpService would return [] if optional methods fail due to my changes
        // but here we are testing if McpDiscoveryService handles [] correctly.
        $mockMcpService = Mockery::mock(McpService::class);
        $mockMcpService->shouldReceive('listTools')->andReturn([]);
        $mockMcpService->shouldReceive('listResources')->andReturn([]);
        $mockMcpService->shouldReceive('getAgentoomPrivacyRecognizers')->andReturn([]);
        $mockMcpService->shouldReceive('getAgentoomElements')->andReturn([]);
        $mockMcpService->shouldReceive('getActionables')->andReturn([]);

        $this->app->instance(McpService::class, $mockMcpService);

        $discoveryService = app(McpDiscoveryService::class);
        $report = $discoveryService->syncServer($server);

        $this->assertIsArray($report['actionables']);
        $this->assertEquals(0, $report['actionables']['imported']);
    }

    public function test_it_removes_actionables_no_longer_present()
    {
        $server = McpServer::create([
            'name' => 'Test Server',
            'url' => 'http://localhost:8080/mcp',
            'transport_type' => 'http',
            'status' => 'active',
        ]);

        McpActionable::create([
            'mcp_server_id' => $server->id,
            'name' => 'Old Action',
            'remote_class' => 'OldClass',
            'remote_method' => 'oldMethod',
        ]);

        $mockMcpService = Mockery::mock(McpService::class);
        $mockMcpService->shouldReceive('listTools')->andReturn([]);
        $mockMcpService->shouldReceive('listResources')->andReturn([]);
        $mockMcpService->shouldReceive('getAgentoomPrivacyRecognizers')->andReturn([]);
        $mockMcpService->shouldReceive('getAgentoomElements')->andReturn([]);
        $mockMcpService->shouldReceive('getActionables')->andReturn([]);

        $this->app->instance(McpService::class, $mockMcpService);

        $discoveryService = app(McpDiscoveryService::class);
        $discoveryService->syncMcpServers();

        $this->assertDatabaseMissing('mcp_actionables', [
            'name' => 'Old Action',
        ]);
    }
}
