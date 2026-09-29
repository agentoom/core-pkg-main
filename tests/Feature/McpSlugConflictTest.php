<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\AgentSkill;
use Agentoom\Core\Models\McpServer;
use Agentoom\Core\Models\Tool;
use Agentoom\Core\Services\McpDiscoveryService;
use Agentoom\Core\Services\McpService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class McpSlugConflictTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_handles_slug_conflicts_gracefully()
    {
        $server = McpServer::create([
            'name' => 'Remote Server',
            'url' => 'http://localhost:8080/mcp',
            'transport_type' => 'http',
            'status' => 'active',
        ]);

        // 1. Mock MCP service to return two skills that result in the same slug on the SAME server
        $mockMcpService = Mockery::mock(McpService::class);
        $mockMcpService->shouldReceive('listTools')->andReturn([
            'tools' => [
                [
                    'name' => 'Search Tool',
                    'description' => 'First search tool',
                ],
                [
                    'name' => 'Search-Tool', // Sanitizes to same safe name
                    'description' => 'Second search tool',
                ],
            ],
        ]);
        $mockMcpService->shouldReceive('listResources')->andReturn([]);
        $mockMcpService->shouldReceive('getActionables')->andReturn([]);
        $mockMcpService->shouldReceive('getAgentoomPrivacyRecognizers')->andReturn([]);
        $mockMcpService->shouldReceive('getAgentoomElements')
            ->andReturn([
                'agents' => [],
                'skills' => [
                    [
                        'name' => 'Meta Ads',
                        'slug' => 'meta-ads',
                        'description' => 'First skill',
                    ],
                    [
                        'name' => 'Meta-Ads', // Different name but same slug manually provided or generated
                        'slug' => 'meta-ads',
                        'description' => 'Second skill with same slug',
                    ],
                    [
                        'name' => 'Other Skill',
                        'description' => 'This should still be imported',
                    ],
                ],
            ]);

        $this->app->instance(McpService::class, $mockMcpService);

        $discoveryService = app(McpDiscoveryService::class);

        // This should NO LONGER throw an exception
        $results = $discoveryService->syncServer($server);

        // One of the Meta Ads skills should be skipped due to slug conflict on same server
        $this->assertCount(2, AgentSkill::where('mcp_server_id', $server->id)->get());
        $this->assertCount(1, $results['skills']['skipped']);
        $this->assertStringContainsString('Meta', $results['skills']['skipped'][0]);

        $this->assertDatabaseHas('agent_skills', [
            'name' => 'Other Skill',
            'mcp_server_id' => $server->id,
        ]);

        // Verify both tools are imported because of hash in class name
        $this->assertEquals(2, Tool::where('mcp_server_id', $server->id)->count());
        $this->assertEquals(2, $results['tools']['imported']);
    }
}
