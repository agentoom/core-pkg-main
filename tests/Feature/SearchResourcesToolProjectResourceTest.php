<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Tools\Public\SearchResourcesTool;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\ProjectResource;
use Agentoom\Core\Models\ProjectResourceType;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Spatie\Permission\Models\Role;

class SearchResourcesToolProjectResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Role::where('name', 'superadmin')->exists()) {
            Role::create(['name' => 'superadmin']);
        }
    }

    public function test_it_can_list_and_search_project_resource_types_and_resources()
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        $this->actingAs($user);

        $project = Project::factory()->create(['user_id' => $user->id]);

        $type = ProjectResourceType::create([
            'project_id' => $project->id,
            'name' => ['en' => 'Project Server'],
            'config' => [
                'fields' => [
                    ['name' => 'ip', 'type' => 'text'],
                ],
            ],
        ]);

        $resource = ProjectResource::create([
            'project_id' => $project->id,
            'project_resource_type_id' => $type->id,
            'name' => 'My Project Server',
            'data' => [
                'ip' => '5.6.7.8',
            ],
        ]);

        $tool = new SearchResourcesTool;

        // Test list_types
        $requestList = new Request([
            'action' => 'list_types',
            'query' => 'Project',
        ]);
        $responseList = $tool->handle($requestList);

        $this->assertStringContainsString('[Project] ID: '.$type->id.' - NAME: Project Server', (string) $responseList);

        // Test search
        $requestSearch = new Request([
            'action' => 'search',
            'query' => 'My Project',
        ]);
        $responseSearch = $tool->handle($requestSearch);

        $this->assertStringContainsString('[Project] Resource: My Project Server', (string) $responseSearch);
        $this->assertStringContainsString('ID: project:'.$resource->id, (string) $responseSearch);
        $this->assertStringContainsString('ip: 5.6.7.8', (string) $responseSearch);
    }
}
