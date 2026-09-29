<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Livewire\Admin\McpServerIndex;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AgentSkill;
use Agentoom\Core\Models\McpServer;
use Agentoom\Core\Models\Tool;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

class McpServerDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_mcp_server_and_always_deletes_tools_and_resources()
    {
        $server = McpServer::create([
            'name' => 'Test Server',
            'url' => 'http://localhost/mcp',
            'transport_type' => 'http',
        ]);

        $tool = Tool::create([
            'mcp_server_id' => $server->id,
            'remote_name' => 'test-tool',
            'class_name' => 'TestTool',
            'name' => 'Test Tool',
            'description' => 'Test Description',
        ]);

        $agent = Agent::create([
            'name' => 'Test Agent',
            'mcp_server_id' => $server->id,
            'remote_name' => 'test-agent',
        ]);

        $agent->tools()->attach($tool->id);

        Livewire::test(McpServerIndex::class)
            ->set('server_id_to_delete', $server->id)
            ->call('delete');

        $this->assertDatabaseMissing('mcp_servers', ['id' => $server->id]);
        $this->assertDatabaseMissing('tools', ['id' => $tool->id]);
        // Tool should be deassigned from agent (pivot record missing)
        $this->assertDatabaseMissing('agent_tool', [
            'agent_id' => $agent->id,
            'tool_id' => $tool->id,
        ]);

        // Agent should NOT be deleted by default
        $this->assertDatabaseHas('agents', ['id' => $agent->id]);
    }

    public function test_it_can_delete_related_agents_and_skills()
    {
        $server = McpServer::create([
            'name' => 'Test Server',
            'url' => 'http://localhost/mcp',
            'transport_type' => 'http',
        ]);

        $agent = Agent::create([
            'name' => 'Test Agent',
            'mcp_server_id' => $server->id,
            'remote_name' => 'test-agent',
        ]);

        $skill = AgentSkill::create([
            'name' => 'Test Skill',
            'mcp_server_id' => $server->id,
            'remote_name' => 'test-skill',
        ]);

        Livewire::test(McpServerIndex::class)
            ->set('server_id_to_delete', $server->id)
            ->set('delete_agents', true)
            ->set('delete_skills', true)
            ->call('delete');

        $this->assertDatabaseMissing('mcp_servers', ['id' => $server->id]);
        $this->assertDatabaseMissing('agents', ['id' => $agent->id]);
        $this->assertDatabaseMissing('agent_skills', ['id' => $skill->id]);
    }

    public function test_it_can_choose_not_to_delete_agents_or_skills()
    {
        $server = McpServer::create([
            'name' => 'Test Server',
            'url' => 'http://localhost/mcp',
            'transport_type' => 'http',
        ]);

        $agent = Agent::create([
            'name' => 'Test Agent',
            'mcp_server_id' => $server->id,
            'remote_name' => 'test-agent',
        ]);

        $skill = AgentSkill::create([
            'name' => 'Test Skill',
            'mcp_server_id' => $server->id,
            'remote_name' => 'test-skill',
        ]);

        Livewire::test(McpServerIndex::class)
            ->set('server_id_to_delete', $server->id)
            ->set('delete_agents', false)
            ->set('delete_skills', false)
            ->call('delete');

        $this->assertDatabaseMissing('mcp_servers', ['id' => $server->id]);
        $this->assertDatabaseHas('agents', ['id' => $agent->id]);
        $this->assertDatabaseHas('agent_skills', ['id' => $skill->id]);
    }
}
