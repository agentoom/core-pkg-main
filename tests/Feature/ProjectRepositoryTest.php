<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Tools\Public\ProjectRepositoryTool;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\ProjectResource;
use Agentoom\Core\Models\ProjectResourceType;
use Agentoom\Core\Models\User;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Tools\Request;

class ProjectRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected Project $project;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->project = Project::factory()->create(['user_id' => $this->user->id]);
        app()->instance('current_project_id', $this->project->id);
    }

    public function test_can_create_project_resource_type(): void
    {
        $tool = new ProjectRepositoryTool;
        $request = new Request([
            'action' => 'create_type',
            'name' => 'Test Type',
            'description' => 'Test Description',
            'config' => [
                ['name' => 'api_key', 'type' => 'password', 'required' => true, 'encrypted' => true],
                ['name' => 'url', 'type' => 'text', 'required' => false],
            ],
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Successfully created project resource type', $result);
        $this->assertDatabaseHas('project_resource_types', [
            'project_id' => $this->project->id,
            'name->en' => 'Test Type',
        ]);
    }

    public function test_can_list_project_resource_types(): void
    {
        ProjectResourceType::create([
            'project_id' => $this->project->id,
            'name' => 'Existing Type',
            'config' => [['name' => 'field1', 'type' => 'text']],
        ]);

        $tool = new ProjectRepositoryTool;
        $request = new Request(['action' => 'list_types']);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Existing Type', $result);
    }

    public function test_can_create_project_resource(): void
    {
        $type = ProjectResourceType::create([
            'project_id' => $this->project->id,
            'name' => 'Test Type',
            'config' => [['name' => 'api_key', 'type' => 'password', 'encrypted' => true]],
        ]);

        $tool = new ProjectRepositoryTool;
        $request = new Request([
            'action' => 'create_resource',
            'project_resource_type_id' => $type->id,
            'name' => 'Test Resource',
            'data' => ['api_key' => 'secret123'],
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Successfully created project resource', $result);
        $this->assertDatabaseHas('project_resources', [
            'project_id' => $this->project->id,
            'project_resource_type_id' => $type->id,
            'name' => 'Test Resource',
        ]);

        $resource = ProjectResource::where('name', 'Test Resource')->first();
        // Check encryption
        $this->assertEquals('secret123', $resource->data['api_key']);
        $this->assertNotEquals('secret123', DB::table('project_resources')->where('id', $resource->id)->value('data'));
    }

    public function test_can_update_project_resource(): void
    {
        $type = ProjectResourceType::create([
            'project_id' => $this->project->id,
            'name' => 'Test Type',
            'config' => [['name' => 'field1', 'type' => 'text']],
        ]);

        $resource = ProjectResource::create([
            'project_id' => $this->project->id,
            'project_resource_type_id' => $type->id,
            'name' => 'Old Name',
            'data' => ['field1' => 'old value'],
        ]);

        $tool = new ProjectRepositoryTool;
        $request = new Request([
            'action' => 'update_resource',
            'id' => $resource->id,
            'name' => 'New Name',
            'data' => ['field1' => 'new value'],
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Successfully updated project resource', $result);
        $resource->refresh();
        $this->assertEquals('New Name', $resource->name);
        $this->assertEquals('new value', $resource->data['field1']);
    }

    public function test_can_delete_project_resource(): void
    {
        $type = ProjectResourceType::create([
            'project_id' => $this->project->id,
            'name' => 'Test Type',
            'config' => [['name' => 'field1', 'type' => 'text']],
        ]);

        $resource = ProjectResource::create([
            'project_id' => $this->project->id,
            'project_resource_type_id' => $type->id,
            'name' => 'To Delete',
            'data' => ['field1' => 'value'],
        ]);

        $tool = new ProjectRepositoryTool;
        $request = new Request([
            'action' => 'delete_resource',
            'id' => $resource->id,
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Successfully deleted', $result);
        $this->assertStringContainsString('project resource', $result);
        $this->assertDatabaseMissing('project_resources', ['id' => $resource->id]);
    }
}
