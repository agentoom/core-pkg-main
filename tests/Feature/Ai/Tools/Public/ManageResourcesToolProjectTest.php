<?php

namespace Agentoom\Core\Tests\Feature\Ai\Tools\Public;

use Agentoom\Core\Ai\Tools\Public\ManageResourcesTool;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

class ManageResourcesToolProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_list_types_belonging_to_a_project(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $project = Project::factory()->create(['user_id' => $user->id]);
        $type = ResourceType::create([
            'name' => 'Project Type',
            'description' => 'A type belonging to a project',
            'config' => [['name' => 'field1', 'type' => 'text']],
            'project_id' => $project->id,
            'user_id' => $user->id,
        ]);

        $tool = new ManageResourcesTool;
        $output = $tool->handle(new Request(['action' => 'list_types']));

        // Currently fails because of whereNull('project_id')
        $this->assertStringContainsString('Project Type', $output);
    }

    public function test_it_can_create_resource_inheriting_project_id_from_type(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $project = Project::factory()->create();
        $type = ResourceType::create([
            'name' => 'Project Type',
            'config' => [['name' => 'field1', 'type' => 'text']],
            'project_id' => $project->id,
        ]);

        $tool = new ManageResourcesTool;
        $output = $tool->handle(new Request([
            'action' => 'create_resource',
            'resource_type_id' => $type->id,
            'name' => 'Test Resource',
            'data' => ['field1' => 'value1'],
        ]));

        // Currently fails because createResource uses whereNull('project_id') to find the type
        $this->assertStringContainsString('Successfully created resource', $output);

        $resource = Resource::latest()->first();
        $this->assertEquals($project->id, $resource->project_id, 'Resource should inherit project_id from type');
    }

    public function test_it_can_update_resource_belonging_to_a_project(): void
    {
        $user = User::factory()->create(['is_system' => true]); // System user/Admin bypasses some checks
        $this->actingAs($user);

        $project = Project::factory()->create();
        $type = ResourceType::create([
            'name' => 'Project Type',
            'config' => [['name' => 'field1', 'type' => 'text']],
            'project_id' => $project->id,
        ]);

        $resource = Resource::create([
            'resource_type_id' => $type->id,
            'name' => 'Existing Resource',
            'data' => ['field1' => 'old_value'],
            'project_id' => $project->id,
            'user_id' => $user->id,
        ]);

        $tool = new ManageResourcesTool;
        $output = $tool->handle(new Request([
            'action' => 'update_resource',
            'resource_id' => $resource->id,
            'data' => ['field1' => 'new_value'],
        ]));

        // Currently fails because updateResource uses whereNull('project_id')
        $this->assertStringContainsString('Successfully updated resource', $output);
        $this->assertEquals('new_value', $resource->fresh()->data['field1']);
    }
}
