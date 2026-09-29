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

class McpAgentNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_assigns_tools_and_skills_with_inconsistent_naming()
    {
        $server = McpServer::create([
            'name' => 'Localhost SEO',
            'url' => 'http://localhost:8080/mcp',
            'transport_type' => 'http',
            'status' => 'active',
        ]);

        // Mock McpService
        $mockMcpService = Mockery::mock(McpService::class);

        // 1. listTools - provides tools with "nice" names or mixed casing
        $mockMcpService->shouldReceive('listTools')->andReturn([
            'tools' => [
                [
                    'name' => 'Cloud-Flare-Tool', // Mixed case and hyphens
                    'description' => 'Cloudflare Integration',
                ],
                [
                    'name' => 'google ads tool', // Lowercase and spaces
                    'description' => 'Google Ads',
                ],
            ],
        ])->byDefault();

        $mockMcpService->shouldReceive('listResources')->andReturn([])->byDefault();
        $mockMcpService->shouldReceive('getActionables')->andReturn([])->byDefault();
        $mockMcpService->shouldReceive('getAgentoomPrivacyRecognizers')->andReturn([])->byDefault();

        // 2. getAgentoomElements - agent uses different formats to refer to them
        $mockMcpService->shouldReceive('getAgentoomElements')
            ->andReturn([
                'agents' => [
                    [
                        'name' => 'SEO Specialist',
                        'description' => 'SEO Specialist',
                        'tools' => [
                            'Agentoom\\Cloud\\Mcp\\Tools\\CloudFlareTool', // Class name format
                            'GoogleAdsTool', // PascalCase format
                        ],
                        'skills' => ['seo-data-layer'], // Slug format
                    ],
                ],
                'skills' => [
                    [
                        'name' => 'SEO Data Layer', // Original name has spaces
                        'description' => 'SEO Data Layer Skill',
                        'instructions' => 'Instructions',
                        'tools' => [
                            'CloudFlareTool',
                        ],
                    ],
                ],
            ])->byDefault();

        $this->app->instance(McpService::class, $mockMcpService);

        $discoveryService = app(McpDiscoveryService::class);
        $discoveryService->syncServer($server);

        // Verify Tools were imported
        $this->assertDatabaseHas('tools', ['remote_name' => 'Cloud-Flare-Tool', 'mcp_server_id' => $server->id]);
        $this->assertDatabaseHas('tools', ['remote_name' => 'google ads tool', 'mcp_server_id' => $server->id]);

        // Verify Skill was imported
        $this->assertDatabaseHas('agent_skills', ['remote_name' => 'SEO Data Layer', 'mcp_server_id' => $server->id]);

        // Verify Agent exists
        $agent = Agent::where('remote_name', 'SEO Specialist')->first();
        $this->assertNotNull($agent);

        // Verify Assignments
        // These are expected to FAIL before the fix
        $this->assertCount(2, $agent->tools, 'Agent should have 2 tools assigned despite naming inconsistency');
        $this->assertCount(1, $agent->skills, 'Agent should have 1 skill assigned despite naming inconsistency');

        $this->assertTrue($agent->tools->contains('remote_name', 'Cloud-Flare-Tool'));
        $this->assertTrue($agent->tools->contains('remote_name', 'google ads tool'));
        $this->assertTrue($agent->skills->contains('remote_name', 'SEO Data Layer'));

        // Verify Skill-Tool mapping
        $skill = AgentSkill::where('remote_name', 'SEO Data Layer')->first();
        $tool = Tool::where('remote_name', 'Cloud-Flare-Tool')->first();
        $this->assertContains($tool->class_name, $skill->tools, 'Skill tools should be correctly mapped');
    }
}
