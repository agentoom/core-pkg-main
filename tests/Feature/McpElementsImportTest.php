<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AgentSkill;
use Agentoom\Core\Models\McpServer;
use Agentoom\Core\Models\Tool;
use Agentoom\Core\Services\McpDiscoveryService;
use Agentoom\Core\Services\McpService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class McpElementsImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_fetch_agentoom_elements()
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
        $mockMcpService->shouldReceive('getActionables')->andReturn([]);
        $mockMcpService->shouldReceive('getAgentoomPrivacyRecognizers')->andReturn([]);
        $mockMcpService->shouldReceive('getAgentoomElements')
            ->andReturn([
                'agents' => [
                    [
                        'name' => 'Remote Agent',
                        'role' => 'Assistant',
                        'instructions' => 'Helpful instructions',
                    ],
                ],
                'skills' => [
                    [
                        'name' => 'Remote Skill',
                        'description' => 'A useful skill',
                        'instructions' => 'Skill instructions',
                    ],
                ],
            ]);

        $this->app->instance(McpService::class, $mockMcpService);

        $discoveryService = app(McpDiscoveryService::class);
        $discoveryService->syncMcpServers();

        $this->assertDatabaseHas('agents', [
            'name' => 'Remote Agent',
            'mcp_server_id' => $server->id,
            'remote_name' => 'Remote Agent',
        ]);

        $this->assertDatabaseHas('agent_skills', [
            'name' => 'Remote Skill',
            'mcp_server_id' => $server->id,
            'remote_name' => 'Remote Skill',
        ]);
    }

    public function test_it_removes_elements_no_longer_present_on_server()
    {
        $server = McpServer::create([
            'name' => 'Test Server',
            'url' => 'http://localhost:8080/mcp',
            'transport_type' => 'http',
            'status' => 'active',
        ]);

        // Create an agent that should be removed
        Agent::create([
            'name' => 'Old Agent',
            'mcp_server_id' => $server->id,
            'remote_name' => 'Old Agent',
        ]);

        $mockMcpService = Mockery::mock(McpService::class);
        $mockMcpService->shouldReceive('listTools')->andReturn([]);
        $mockMcpService->shouldReceive('listResources')->andReturn([]);
        $mockMcpService->shouldReceive('getActionables')->andReturn([]);
        $mockMcpService->shouldReceive('getAgentoomPrivacyRecognizers')->andReturn([]);
        $mockMcpService->shouldReceive('getAgentoomElements')
            ->andReturn([
                'agents' => [],
                'skills' => [],
            ]);

        $this->app->instance(McpService::class, $mockMcpService);

        $discoveryService = app(McpDiscoveryService::class);
        $discoveryService->syncMcpServers();

        $this->assertDatabaseMissing('agents', [
            'name' => 'Old Agent',
        ]);
    }

    public function test_skill_tools_are_imported_from_mcp_server()
    {
        $server = McpServer::create([
            'name' => 'Test Server',
            'url' => 'http://localhost:8080/mcp',
            'transport_type' => 'http',
            'status' => 'active',
        ]);

        $mockMcpService = Mockery::mock(McpService::class);
        // Tools from tools/list — these become standalone Tool records
        $mockMcpService->shouldReceive('listTools')
            ->andReturn([
                'tools' => [
                    [
                        'name' => 'Search Tool',
                        'description' => 'Searches the web',
                        'inputSchema' => [],
                    ],
                    [
                        'name' => 'Weather Tool',
                        'description' => 'Gets the weather',
                        'inputSchema' => [],
                    ],
                ],
            ]);
        $mockMcpService->shouldReceive('listResources')->andReturn([]);
        $mockMcpService->shouldReceive('getActionables')->andReturn([]);
        $mockMcpService->shouldReceive('getAgentoomPrivacyRecognizers')->andReturn([]);

        // Skills reference tools by name (remote_name)
        $mockMcpService->shouldReceive('getAgentoomElements')
            ->andReturn([
                'agents' => [],
                'skills' => [
                    [
                        'name' => 'Research Skill',
                        'description' => 'A research skill',
                        'instructions' => 'Do research',
                        'tools' => ['Search Tool', 'Weather Tool'],
                    ],
                ],
            ]);

        $this->app->instance(McpService::class, $mockMcpService);

        $discoveryService = app(McpDiscoveryService::class);
        $discoveryService->syncMcpServers();

        // Verify tools were imported
        $this->assertDatabaseHas('tools', [
            'remote_name' => 'Search Tool',
            'mcp_server_id' => $server->id,
        ]);
        $this->assertDatabaseHas('tools', [
            'remote_name' => 'Weather Tool',
            'mcp_server_id' => $server->id,
        ]);

        // Verify skill was imported
        $skill = AgentSkill::where('remote_name', 'Research Skill')->first();
        $this->assertNotNull($skill, 'Skill was not imported');

        // *** THE KEY ASSERTION: tools should be stored in the skill's tools column ***
        $this->assertNotEmpty($skill->tools, 'Skill tools should not be empty');
        $this->assertCount(2, $skill->tools, 'Skill should have 2 tools');

        // Verify the tools are resolved to class names
        $searchTool = Tool::where('remote_name', 'Search Tool')->first();
        $weatherTool = Tool::where('remote_name', 'Weather Tool')->first();

        $this->assertContains($searchTool->class_name, $skill->tools);
        $this->assertContains($weatherTool->class_name, $skill->tools);
    }
}
