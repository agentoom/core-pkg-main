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

class McpAgentAssignmentClearingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_clears_tools_and_skills_when_removed_from_mcp_server()
    {
        $server = McpServer::create([
            'name' => 'Test Server',
            'url' => 'http://localhost:8080/mcp',
            'transport_type' => 'http',
            'status' => 'active',
        ]);

        $tool = Tool::create([
            'name' => 'Existing Tool',
            'class_name' => 'SomeClass',
            'description' => 'desc',
            'mcp_server_id' => $server->id,
            'remote_name' => 'Existing Tool',
        ]);

        $skill = AgentSkill::create([
            'name' => 'Existing Skill',
            'description' => 'desc',
            'instructions' => 'instr',
            'tools' => [],
            'mcp_server_id' => $server->id,
            'remote_name' => 'Existing Skill',
        ]);

        $agent = Agent::create([
            'name' => 'Test Agent',
            'mcp_server_id' => $server->id,
            'remote_name' => 'Test Agent',
        ]);

        $agent->tools()->attach($tool->id);
        $agent->skills()->attach($skill->id);

        $this->assertCount(1, $agent->tools);
        $this->assertCount(1, $agent->skills);

        // Mock response with NO tools and NO skills for the agent
        $mockMcpService = Mockery::mock(McpService::class);
        $mockMcpService->shouldReceive('listTools')->andReturn([
            'tools' => [
                ['name' => 'Existing Tool', 'description' => 'desc'],
            ],
        ]);
        $mockMcpService->shouldReceive('listResources')->andReturn([]);
        $mockMcpService->shouldReceive('getActionables')->andReturn([]);
        $mockMcpService->shouldReceive('getAgentoomPrivacyRecognizers')->andReturn([]);
        $mockMcpService->shouldReceive('getAgentoomElements')
            ->andReturn([
                'agents' => [
                    [
                        'name' => 'Test Agent',
                        'description' => 'Still here',
                        'tools' => [], // Empty
                        'skills' => [], // Empty
                    ],
                ],
                'skills' => [
                    [
                        'name' => 'Existing Skill',
                        'description' => 'Still here',
                        'tools' => [],
                    ],
                ],
            ]);

        $this->app->instance(McpService::class, $mockMcpService);

        $discoveryService = app(McpDiscoveryService::class);
        $discoveryService->syncServer($server, true); // Use overwrite to update the agent description

        $agent->refresh();
        $this->assertEquals('Still here', $agent->description);
        $this->assertCount(0, $agent->tools, 'Tools should have been cleared');
        $this->assertCount(0, $agent->skills, 'Skills should have been cleared');
    }
}
