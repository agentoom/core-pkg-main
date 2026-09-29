<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AgentSkill;
use Agentoom\Core\Models\McpServer;
use Agentoom\Core\Services\McpDiscoveryService;
use Agentoom\Core\Services\McpService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class McpSkillSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prefixes_skill_slug_with_server_id_and_matches_it_to_agents()
    {
        $server = McpServer::create([
            'id' => 123,
            'name' => 'Test Server',
            'url' => 'http://localhost:8080/mcp',
            'transport_type' => 'http',
            'status' => 'active',
        ]);

        $mockMcpService = Mockery::mock(McpService::class);
        $mockMcpService->shouldReceive('listTools')->andReturn(['tools' => []]);
        $mockMcpService->shouldReceive('listResources')->andReturn([]);
        $mockMcpService->shouldReceive('getActionables')->andReturn([]);
        $mockMcpService->shouldReceive('getAgentoomPrivacyRecognizers')->andReturn([]);

        // Mock elements with frontmatter and specific slug
        $mockMcpService->shouldReceive('getAgentoomElements')
            ->andReturn([
                'agents' => [
                    [
                        'name' => 'SEO Agent',
                        'skills' => ['google-seo'], // referencing by slug
                    ],
                ],
                'skills' => [
                    [
                        'name' => 'Google SEO Integration',
                        'slug' => 'google-seo',
                        'description' => 'Top level description',
                        'instructions' => "---\nname: google-seo-internal\ndescription: Internal description from frontmatter\n---\n\n# Actual instructions",
                        'tools' => [],
                    ],
                ],
            ]);

        $this->app->instance(McpService::class, $mockMcpService);

        $discoveryService = app(McpDiscoveryService::class);
        $discoveryService->syncServer($server);

        // 1. Check skill slug prefixing
        $skill = AgentSkill::where('remote_name', 'Google SEO Integration')->first();
        $this->assertNotNull($skill, 'Skill was not imported');
        $this->assertEquals($server->id.'-google-seo', $skill->slug, 'Skill slug should be prefixed with server ID');

        // 2. Check instructions frontmatter removal and field placement
        $this->assertEquals('google-seo-internal', $skill->name, 'Name should be taken from frontmatter');
        $this->assertEquals('Internal description from frontmatter', $skill->description, 'Description should be taken from frontmatter');
        $this->assertStringNotContainsString('---', $skill->instructions);
        $this->assertStringContainsString('# Actual instructions', $skill->instructions);

        // User said: "name and description of skills is added to the main instructions on import instead of be placed in the respective fields"
        // This might mean we should prefer frontmatter if present?
        // Or at least ensure instructions field doesn't have the redundant info.

        // 3. Check agent assignment by slug
        $agent = Agent::where('name', 'SEO Agent')->first();
        $this->assertNotNull($agent);
        $this->assertCount(1, $agent->skills, 'Agent should have the skill assigned by matching its original slug to the prefixed one');
        $this->assertEquals($skill->id, $agent->skills->first()->id);
    }
}
