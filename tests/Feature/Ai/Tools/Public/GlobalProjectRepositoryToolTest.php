<?php

namespace Agentoom\Core\Tests\Feature\Ai\Tools\Public;

use Agentoom\Core\Ai\Tools\Public\GlobalProjectRepositoryTool;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\ProjectResource;
use Agentoom\Core\Models\ProjectResourceType;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

class GlobalProjectRepositoryToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_list_projects(): void
    {
        Project::factory()->create(['name' => 'Project A']);
        Project::factory()->create(['name' => 'Project B']);

        $tool = new GlobalProjectRepositoryTool;
        $output = $tool->handle(new Request(['action' => 'list_projects']));

        $this->assertStringContainsString('Project A', $output);
        $this->assertStringContainsString('Project B', $output);
    }

    public function test_it_can_list_types_for_a_specific_project(): void
    {
        $project = Project::factory()->create();
        ProjectResourceType::create([
            'project_id' => $project->id,
            'name' => ['en' => 'Test Type'],
            'description' => 'Test Description',
            'config' => [['name' => 'field1', 'type' => 'text', 'required' => true]],
        ]);

        $tool = new GlobalProjectRepositoryTool;
        $output = $tool->handle(new Request([
            'action' => 'list_types',
            'project_id' => $project->id,
        ]));

        $this->assertStringContainsString('Test Type', $output);
        $this->assertStringContainsString('field1', $output);
    }

    public function test_it_can_create_type_for_a_specific_project(): void
    {
        $project = Project::factory()->create();

        $tool = new GlobalProjectRepositoryTool;
        $output = $tool->handle(new Request([
            'action' => 'create_type',
            'project_id' => $project->id,
            'name' => 'New Type',
            'config' => [['name' => 'f1', 'type' => 'number']],
        ]));

        $this->assertStringContainsString('Successfully created project resource type', $output);
        $this->assertEquals('New Type', ProjectResourceType::where('project_id', $project->id)->first()?->name_translated);
    }

    public function test_it_can_create_resource_for_a_specific_project(): void
    {
        $project = Project::factory()->create();
        $type = ProjectResourceType::create([
            'project_id' => $project->id,
            'name' => ['en' => 'Type'],
            'config' => [['name' => 'k1', 'type' => 'text', 'required' => true]],
        ]);

        $tool = new GlobalProjectRepositoryTool;
        $output = $tool->handle(new Request([
            'action' => 'create_resource',
            'project_id' => $project->id,
            'project_resource_type_id' => $type->id,
            'name' => 'Res 1',
            'data' => ['k1' => 'v1'],
        ]));

        $this->assertStringContainsString('Successfully created project resource', $output);
        $this->assertEquals('Res 1', ProjectResource::where('project_id', $project->id)->first()?->name);
    }

    public function test_it_can_list_resources_across_projects(): void
    {
        $p1 = Project::factory()->create(['name' => 'P1']);
        $p2 = Project::factory()->create(['name' => 'P2']);

        $t1 = ProjectResourceType::create(['project_id' => $p1->id, 'name' => ['en' => 'T1'], 'config' => []]);
        $t2 = ProjectResourceType::create(['project_id' => $p2->id, 'name' => ['en' => 'T2'], 'config' => []]);

        ProjectResource::create(['project_id' => $p1->id, 'project_resource_type_id' => $t1->id, 'name' => 'R1', 'data' => []]);
        ProjectResource::create(['project_id' => $p2->id, 'project_resource_type_id' => $t2->id, 'name' => 'R2', 'data' => []]);

        $tool = new GlobalProjectRepositoryTool;
        $output = $tool->handle(new Request(['action' => 'list_resources']));

        $this->assertStringContainsString('R1', $output);
        $this->assertStringContainsString('P1', $output);
        $this->assertStringContainsString('R2', $output);
        $this->assertStringContainsString('P2', $output);
    }

    public function test_it_can_update_resource(): void
    {
        $project = Project::factory()->create();
        $type = ProjectResourceType::create(['project_id' => $project->id, 'name' => ['en' => 'T1'], 'config' => []]);
        $resource = ProjectResource::create([
            'project_id' => $project->id,
            'project_resource_type_id' => $type->id,
            'name' => 'Old Name',
            'data' => ['a' => 1],
        ]);

        $tool = new GlobalProjectRepositoryTool;
        $output = $tool->handle(new Request([
            'action' => 'update_resource',
            'id' => $resource->id,
            'name' => 'New Name',
            'data' => ['b' => 2],
        ]));

        $this->assertStringContainsString('Successfully updated project resource', $output);
        $resource->refresh();
        $this->assertEquals('New Name', $resource->name);
        $this->assertEquals(['a' => 1, 'b' => 2], $resource->data);
    }

    public function test_it_can_delete_resource(): void
    {
        $project = Project::factory()->create();
        $type = ProjectResourceType::create(['project_id' => $project->id, 'name' => ['en' => 'T1'], 'config' => []]);
        $resource = ProjectResource::create([
            'project_id' => $project->id,
            'project_resource_type_id' => $type->id,
            'name' => 'To Delete',
            'data' => [],
        ]);

        $tool = new GlobalProjectRepositoryTool;
        $output = $tool->handle(new Request([
            'action' => 'delete_resource',
            'id' => $resource->id,
        ]));

        $this->assertStringContainsString('Successfully deleted 1 project resource(s)', $output);
        $this->assertDatabaseMissing('project_resources', ['id' => $resource->id]);
    }

    public function test_it_can_delete_multiple_resources(): void
    {
        $project = Project::factory()->create();
        $type = ProjectResourceType::create(['project_id' => $project->id, 'name' => ['en' => 'T1'], 'config' => []]);
        $r1 = ProjectResource::create(['project_id' => $project->id, 'project_resource_type_id' => $type->id, 'name' => 'R1', 'data' => []]);
        $r2 = ProjectResource::create(['project_id' => $project->id, 'project_resource_type_id' => $type->id, 'name' => 'R2', 'data' => []]);
        $r3 = ProjectResource::create(['project_id' => $project->id, 'project_resource_type_id' => $type->id, 'name' => 'R3', 'data' => []]);

        $tool = new GlobalProjectRepositoryTool;

        // Test with array of IDs
        $output = $tool->handle(new Request([
            'action' => 'delete',
            'ids' => [$r1->id, $r2->id],
        ]));

        $this->assertStringContainsString('Successfully deleted 2 project resource(s)', $output);
        $this->assertDatabaseMissing('project_resources', ['id' => $r1->id]);
        $this->assertDatabaseMissing('project_resources', ['id' => $r2->id]);
        $this->assertDatabaseHas('project_resources', ['id' => $r3->id]);

        // Test with comma-separated string
        $output = $tool->handle(new Request([
            'action' => 'delete',
            'ids' => (string) $r3->id,
        ]));
        $this->assertStringContainsString('Successfully deleted 1 project resource(s)', $output);
        $this->assertDatabaseMissing('project_resources', ['id' => $r3->id]);
    }

    public function test_it_can_handle_robust_id_formats_for_deletion(): void
    {
        $project = Project::factory()->create();
        $type = ProjectResourceType::create(['project_id' => $project->id, 'name' => ['en' => 'T1'], 'config' => []]);
        $r1 = ProjectResource::create(['project_id' => $project->id, 'project_resource_type_id' => $type->id, 'name' => 'R1', 'data' => []]);
        $r2 = ProjectResource::create(['project_id' => $project->id, 'project_resource_type_id' => $type->id, 'name' => 'R2', 'data' => []]);

        $tool = new GlobalProjectRepositoryTool;

        // JSON array string
        $output = $tool->handle(new Request([
            'action' => 'delete',
            'ids' => json_encode([$r1->id, $r2->id]),
        ]));

        $this->assertStringContainsString('Successfully deleted 2 project resource(s)', $output);
        $this->assertDatabaseMissing('project_resources', ['id' => $r1->id]);
        $this->assertDatabaseMissing('project_resources', ['id' => $r2->id]);
    }

    public function test_it_can_delete_resource_type(): void
    {
        $project = Project::factory()->create();
        $type = ProjectResourceType::create([
            'project_id' => $project->id,
            'name' => ['en' => 'To Delete'],
            'config' => [],
        ]);

        $tool = new GlobalProjectRepositoryTool;
        $output = $tool->handle(new Request([
            'action' => 'delete_type',
            'id' => $type->id,
        ]));

        $this->assertStringContainsString('Successfully deleted 1 project resource type(s)', $output);
        $this->assertDatabaseMissing('project_resource_types', ['id' => $type->id]);
    }

    public function test_it_can_delete_multiple_resource_types(): void
    {
        $project = Project::factory()->create();
        $t1 = ProjectResourceType::create(['project_id' => $project->id, 'name' => ['en' => 'T1'], 'config' => []]);
        $t2 = ProjectResourceType::create(['project_id' => $project->id, 'name' => ['en' => 'T2'], 'config' => []]);

        $tool = new GlobalProjectRepositoryTool;
        $output = $tool->handle(new Request([
            'action' => 'delete_type',
            'ids' => [$t1->id, $t2->id],
        ]));

        $this->assertStringContainsString('Successfully deleted 2 project resource type(s)', $output);
        $this->assertDatabaseMissing('project_resource_types', ['id' => $t1->id]);
        $this->assertDatabaseMissing('project_resource_types', ['id' => $t2->id]);
    }

    public function test_it_can_update_resource_type(): void
    {
        $project = Project::factory()->create();
        $type = ProjectResourceType::create([
            'project_id' => $project->id,
            'name' => ['en' => 'Old Name'],
            'description' => 'Old Description',
            'config' => [['name' => 'field1', 'type' => 'text']],
        ]);

        $tool = new GlobalProjectRepositoryTool;
        $output = $tool->handle(new Request([
            'action' => 'update_type',
            'id' => $type->id,
            'name' => 'New Name',
            'description' => 'New Description',
            'config' => [['name' => 'field2', 'type' => 'number']],
        ]));

        $this->assertStringContainsString('Successfully updated project resource type: \'New Name\'', $output);
        $this->assertDatabaseHas('project_resource_types', [
            'id' => $type->id,
            'description' => 'New Description',
        ]);

        $type->refresh();
        $this->assertEquals('New Name', $type->name_translated);
        $this->assertEquals([['name' => 'field2', 'type' => 'number']], $type->config);
    }
}
