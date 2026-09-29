<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\McpServer;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class McpServerProjectBindingTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_is_auto_created_when_mcp_server_is_created()
    {
        $server = McpServer::create([
            'name' => 'Auto Project Server',
            'url' => 'http://localhost/mcp',
            'transport_type' => 'http',
        ]);

        $this->assertNotNull($server->project_id);
        $this->assertDatabaseHas('projects', [
            'id' => $server->project_id,
            'name' => 'MCP: Auto Project Server',
            'is_active' => true,
        ]);
    }

    public function test_project_is_not_created_when_explicitly_set_on_create()
    {
        $user = User::factory()->create();
        $existingProject = Project::create([
            'name' => 'Existing Project',
            'is_active' => true,
            'user_id' => $user->id,
        ]);

        $server = McpServer::create([
            'name' => 'Prebound Server',
            'url' => 'http://localhost/mcp',
            'transport_type' => 'http',
            'project_id' => $existingProject->id,
        ]);

        $this->assertEquals($existingProject->id, $server->project_id);
        $this->assertEquals(1, Project::count(), 'No extra project should be created');
    }

    public function test_project_is_auto_created_on_update_when_mcp_server_has_none()
    {
        // Create a server without triggering the creating hook's project auto-bind
        // by explicitly setting a project_id to null
        $server = McpServer::create([
            'name' => 'Updatable Server',
            'url' => 'http://localhost/mcp',
            'transport_type' => 'http',
        ]);

        // Verify project was created on create
        $this->assertNotNull($server->project_id);
        $projectId = $server->project_id;

        // Force-remove the project_id (simulating an old server without a project)
        $server->project_id = null;
        $server->saveQuietly();

        $this->assertNull($server->fresh()->project_id);

        // Now update the server (this triggers the updating hook)
        $server->update([
            'last_synced_at' => now(),
            'status' => 'active',
        ]);

        $server->refresh();
        $this->assertNotNull($server->project_id);
        $this->assertNotEquals($projectId, $server->project_id, 'A new project should have been created');
        $this->assertDatabaseHas('projects', [
            'id' => $server->project_id,
            'name' => 'MCP: Updatable Server',
            'is_active' => true,
        ]);
    }

    public function test_project_is_not_duplicated_on_update_when_already_bound()
    {
        $server = McpServer::create([
            'name' => 'Already Bound Server',
            'url' => 'http://localhost/mcp',
            'transport_type' => 'http',
        ]);

        $this->assertNotNull($server->project_id);
        $projectCount = Project::count();

        // Update the server — should NOT create another project
        $server->update([
            'last_synced_at' => now(),
            'status' => 'active',
        ]);

        $this->assertEquals($projectCount, Project::count(), 'No extra project should be created');
        $this->assertEquals($server->project_id, $server->fresh()->project_id);
    }

    public function test_project_survives_mcp_server_deletion()
    {
        $server = McpServer::create([
            'name' => 'Deletable Server',
            'url' => 'http://localhost/mcp',
            'transport_type' => 'http',
        ]);

        $projectId = $server->project_id;
        $this->assertNotNull($projectId);

        $server->delete();

        $this->assertDatabaseMissing('mcp_servers', ['id' => $server->id]);
        $this->assertDatabaseHas('projects', ['id' => $projectId]);
    }
}
