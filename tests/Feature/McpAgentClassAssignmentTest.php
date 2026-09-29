<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\McpServer;
use Agentoom\Core\Models\Tool;
use Agentoom\Core\Services\McpDiscoveryService;
use Agentoom\Core\Services\McpService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class McpAgentClassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_assigns_tools_and_skills_to_imported_agent_and_maps_skill_tools()
    {
        $server = McpServer::create([
            'name' => 'SEO Company',
            'url' => 'http://localhost:8080/mcp',
            'transport_type' => 'http',
            'status' => 'active',
        ]);

        // Mock McpService
        $mockMcpService = Mockery::mock(McpService::class);

        // 1. listTools
        $mockMcpService->shouldReceive('listTools')->andReturn([
            'tools' => [
                [
                    'name' => 'AssignAiTaskTool',
                    'description' => 'Assign tasks',
                ],
                [
                    'name' => 'ProjectRepositoryTool',
                    'description' => 'Repo',
                ],
            ],
        ]);

        $mockMcpService->shouldReceive('listResources')->andReturn([]);
        $mockMcpService->shouldReceive('getActionables')->andReturn([]);
        $mockMcpService->shouldReceive('getAgentoomPrivacyRecognizers')->andReturn([]);

        // 2. getAgentoomElements
        $mockMcpService->shouldReceive('getAgentoomElements')
            ->andReturn([
                'agents' => [
                    [
                        'name' => 'SEO CEO',
                        'description' => 'Chief',
                        'tools' => [
                            'Agentoom\\Core\\Ai\\Tools\\Public\\AssignAiTaskTool',
                            'Agentoom\\Core\\Ai\\Tools\\Public\\ProjectRepositoryTool',
                        ],
                        'skills' => ['seo-strategy'],
                    ],
                ],
                'skills' => [
                    [
                        'name' => 'seo-strategy',
                        'description' => 'SEO Strategy Skill',
                        'instructions' => 'Strategize',
                        'tools' => [
                            'Agentoom\\Core\\Ai\\Tools\\Public\\ProjectRepositoryTool',
                        ],
                    ],
                ],
            ]);

        $this->app->instance(McpService::class, $mockMcpService);

        $discoveryService = app(McpDiscoveryService::class);
        $discoveryService->syncServer($server);

        // Verify Agent Tools
        $agent = Agent::where('remote_name', 'SEO CEO')->first();
        $this->assertNotNull($agent);
        $this->assertCount(2, $agent->tools, 'Tools should be assigned even when using full class names');
        $this->assertTrue($agent->tools->contains('remote_name', 'AssignAiTaskTool'));
        $this->assertTrue($agent->tools->contains('remote_name', 'ProjectRepositoryTool'));

        // Verify Agent Skills
        $this->assertCount(1, $agent->skills);
        $skill = $agent->skills->first();
        $this->assertEquals('seo-strategy', $skill->remote_name);

        // Verify Skill Tools Mapping
        // It should have been mapped to the local dynamic class name
        $tool = Tool::where('remote_name', 'ProjectRepositoryTool')->first();
        $this->assertNotNull($tool);
        $this->assertContains($tool->class_name, $skill->tools, 'Skill tools should be mapped to local dynamic class names');
    }
}
