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

class McpLocalSkillAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_assigns_local_skills_and_tools_to_imported_agents_if_slug_matches()
    {
        // 1. Create a local skill
        $localSkill = AgentSkill::create([
            'name' => 'Web Browsing',
            'slug' => 'web-browsing',
            'instructions' => 'Instructions',
        ]);

        // 2. Create a local tool
        $localTool = Tool::create([
            'name' => 'SMTP Tool',
            'description' => 'Send emails via SMTP',
            'remote_name' => 'Agentoom\Core\Ai\Tools\Public\SMTPTool',
            'class_name' => 'Agentoom\Core\Ai\Tools\Public\SMTPTool',
            'input_schema' => [],
        ]);

        $server = McpServer::create([
            'id' => 1,
            'name' => 'SEO Server',
            'url' => 'http://localhost:8080/mcp',
            'transport_type' => 'http',
            'status' => 'active',
        ]);

        $mockMcpService = Mockery::mock(McpService::class);
        $mockMcpService->shouldReceive('listTools')->andReturn(['tools' => []]);
        $mockMcpService->shouldReceive('listResources')->andReturn(['resources' => []]);
        $mockMcpService->shouldReceive('getActionables')->andReturn([]);
        $mockMcpService->shouldReceive('getAgentoomPrivacyRecognizers')->andReturn([]);
        $mockMcpService->shouldReceive('getAgentoomElements')->andReturn([
            'agents' => [
                [
                    'name' => 'SEO Agent',
                    'skills' => ['web-browsing'],
                    'tools' => ['Agentoom\Core\Ai\Tools\Public\SMTPTool'],
                ],
            ],
            'skills' => [], // No skills on this server
        ]);

        $this->app->instance(McpService::class, $mockMcpService);

        $discoveryService = app(McpDiscoveryService::class);
        $discoveryService->syncServer($server);

        $agent = Agent::where('name', 'SEO Agent')->first();
        $this->assertNotNull($agent);

        // Check skill
        $this->assertCount(1, $agent->skills, 'Agent should have the local skill assigned');
        $this->assertEquals($localSkill->id, $agent->skills->first()->id);

        // Check tool
        $this->assertCount(1, $agent->tools, 'Agent should have the local tool assigned');
        $this->assertEquals($localTool->id, $agent->tools->first()->id);
    }
}
