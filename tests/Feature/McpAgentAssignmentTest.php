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

class McpAgentAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_assigns_tools_and_skills_to_imported_agent()
    {
        $server = McpServer::create([
            'name' => 'Test Server',
            'url' => 'http://localhost:8080/mcp',
            'transport_type' => 'http',
            'status' => 'active',
        ]);

        // Mock tools and elements response
        $mockMcpService = Mockery::mock(McpService::class);

        // 1. tools/list
        $mockMcpService->shouldReceive('listTools')->andReturn([
            'tools' => [
                [
                    'name' => 'Search Tool',
                    'description' => 'A search tool',
                ],
                [
                    'name' => 'Calculator Tool',
                    'description' => 'A calculator tool',
                ],
            ],
        ])->byDefault();

        $mockMcpService->shouldReceive('listResources')->andReturn([])->byDefault();
        $mockMcpService->shouldReceive('getActionables')->andReturn([])->byDefault();
        $mockMcpService->shouldReceive('getAgentoomPrivacyRecognizers')->andReturn([])->byDefault();

        // 2. agentoom/elements
        $mockMcpService->shouldReceive('getAgentoomElements')
            ->andReturn([
                'agents' => [
                    [
                        'name' => 'All-in-one Agent',
                        'role' => 'Versatile Assistant',
                        'instructions' => 'Use tools and skills',
                        'tools' => ['Search Tool', 'Calculator Tool'],
                        'skills' => ['Browsing Skill'],
                    ],
                ],
                'skills' => [
                    [
                        'name' => 'Browsing Skill',
                        'description' => 'Ability to browse',
                        'instructions' => 'Browse instructions',
                        'tools' => ['Search Tool'],
                    ],
                ],
            ])->byDefault();

        $this->app->instance(McpService::class, $mockMcpService);

        $discoveryService = app(McpDiscoveryService::class);
        $discoveryService->syncServer($server);

        // Verify Agent exists
        $agent = Agent::where('remote_name', 'All-in-one Agent')
            ->where('mcp_server_id', $server->id)
            ->first();

        $this->assertNotNull($agent, 'Agent was not imported');

        // Verify Skill exists
        $skill = AgentSkill::where('remote_name', 'Browsing Skill')
            ->where('mcp_server_id', $server->id)
            ->first();

        $this->assertNotNull($skill, 'Skill was not imported');

        // Verify Tools were imported
        $this->assertDatabaseHas('tools', ['remote_name' => 'Search Tool', 'mcp_server_id' => $server->id]);
        $this->assertDatabaseHas('tools', ['remote_name' => 'Calculator Tool', 'mcp_server_id' => $server->id]);

        // CHECK ASSIGNMENTS (This is what is expected to fail)
        $this->assertCount(2, $agent->tools, 'Agent should have 2 tools assigned');
        $this->assertCount(1, $agent->skills, 'Agent should have 1 skill assigned');

        $this->assertTrue($agent->tools->contains('remote_name', 'Search Tool'));
        $this->assertTrue($agent->tools->contains('remote_name', 'Calculator Tool'));
        $this->assertTrue($agent->skills->contains('remote_name', 'Browsing Skill'));

        // TEST CASE SENSITIVITY AND TRIMMING
        $mockMcpService->shouldReceive('getAgentoomElements')
            ->andReturn([
                'agents' => [
                    [
                        'name' => 'Case Sensitive Agent',
                        'tools' => [' search tool '], // lowercase and padded
                        'skills' => [' BROWSING SKILL '], // uppercase and padded
                    ],
                ],
                'skills' => [
                    [
                        'name' => 'Browsing Skill',
                        'description' => 'Ability to browse',
                        'instructions' => 'Browse instructions',
                        'tools' => ['Search Tool'],
                    ],
                ],
            ]);

        $discoveryService->syncServer($server, true);

        $caseAgent = Agent::where('remote_name', 'Case Sensitive Agent')->first();
        $this->assertNotNull($caseAgent);

        // This will pass if the DB is case-insensitive or if we handle it.
        // My fix added trim(), but not lowercase.
        // If the DB is case-sensitive (like SQLite), it might still fail if not handled.
        $this->assertCount(1, $caseAgent->tools, 'Should match trimmed tool name');
        $this->assertCount(1, $caseAgent->skills, 'Should match trimmed skill name');

        // Bonus: Check skill's tools (stored as JSON)
        $tool = Tool::where('remote_name', 'Search Tool')->first();
        $this->assertEquals([$tool->class_name], $skill->tools, 'Skill should have tools in JSON field');
    }
}
