<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Tools\Public\ManageResourcesTool;
use Agentoom\Core\Ai\Tools\Public\ProjectRepositoryTool;
use Agentoom\Core\Ai\Tools\Public\SearchResourcesTool;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\ProjectResourceType;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

class ResourceToolPrefixHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_manage_resources_tool_handles_global_prefix(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $type = ResourceType::create([
            'name' => 'Global Type',
            'config' => [['name' => 'key', 'type' => 'text']],
        ]);

        $tool = new ManageResourcesTool;

        // Test create_resource with global: prefix
        $output = $tool->handle(new Request([
            'action' => 'create_resource',
            'resource_type_id' => "global:{$type->id}",
            'name' => 'Test Resource',
            'data' => ['key' => 'val'],
        ]));

        $this->assertStringContainsString('Successfully created resource', $output);
        $this->assertStringContainsString("of type 'Global Type'", $output);
    }

    public function test_project_repository_tool_handles_project_prefix(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $project = Project::create(['name' => 'Test Project', 'user_id' => $user->id]);
        app()->instance('current_project_id', $project->id);

        $type = ProjectResourceType::create([
            'project_id' => $project->id,
            'name' => 'Project Type',
            'config' => [['name' => 'key', 'type' => 'text']],
        ]);

        $tool = new ProjectRepositoryTool;

        // Test create_resource with project: prefix
        $output = $tool->handle(new Request([
            'action' => 'create_resource',
            'project_resource_type_id' => "project:{$type->id}",
            'name' => 'Project Resource',
            'data' => ['key' => 'val'],
        ]));

        $this->assertStringContainsString('Successfully created project resource', $output);
        $this->assertStringContainsString("'Project Resource'", $output);
    }

    public function test_search_resources_tool_uses_prefixes_in_output(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $globalType = ResourceType::create([
            'name' => 'Global Type',
            'config' => [['name' => 'key', 'type' => 'text']],
        ]);

        $project = Project::create(['name' => 'Test Project', 'user_id' => $user->id]);
        $projectType = ProjectResourceType::create([
            'project_id' => $project->id,
            'name' => 'Project Type',
            'config' => [['name' => 'key', 'type' => 'text']],
        ]);

        $tool = new SearchResourcesTool;

        $output = $tool->handle(new Request(['action' => 'list_types']));

        $this->assertStringContainsString("### [Global] ID: global:{$globalType->id}", $output);
        $this->assertStringContainsString("### [Project] ID: project:{$projectType->id}", $output);
    }
}
