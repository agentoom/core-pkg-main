<?php

namespace Agentoom\Core\Tests\Feature\Services;

use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\ProjectResource;
use Agentoom\Core\Models\ProjectResourceType;
use Agentoom\Core\Services\ExportService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_project_resources_includes_data(): void
    {
        $project = Project::factory()->create(['name' => 'Test Project']);
        $type = ProjectResourceType::create([
            'name' => ['en' => 'Credentials'],
            'project_id' => $project->id,
            'config' => ['fields' => [['name' => 'api_key', 'type' => 'text']]],
        ]);

        $resource = ProjectResource::create([
            'name' => 'Forge',
            'description' => 'Test description',
            'project_id' => $project->id,
            'project_resource_type_id' => $type->id,
            'data' => ['api_key' => 'secret-value'],
        ]);

        $exportService = app(ExportService::class);
        $data = $exportService->export(['project_resources' => [$resource->id]]);

        $this->assertArrayHasKey('project_resources', $data);
        $this->assertCount(1, $data['project_resources']);
        $this->assertEquals('Forge', $data['project_resources'][0]['name']);
        $this->assertEquals('Test description', $data['project_resources'][0]['description']);
        $this->assertEquals(['api_key' => 'secret-value'], $data['project_resources'][0]['data']);
        $this->assertEquals('Test Project', $data['project_resources'][0]['project_name']);

        $typeName = $data['project_resources'][0]['resource_type_name'];
        $this->assertEquals('Credentials', is_array($typeName) ? $typeName['en'] : $typeName);
    }

    public function test_export_project_resource_types_includes_config(): void
    {
        $project = Project::factory()->create(['name' => 'Test Project']);
        $type = ProjectResourceType::create([
            'name' => ['en' => 'Credentials'],
            'project_id' => $project->id,
            'config' => ['fields' => [['name' => 'api_key', 'type' => 'text']]],
        ]);

        $exportService = app(ExportService::class);
        $data = $exportService->export(['project_resource_types' => [$type->id]]);

        $this->assertArrayHasKey('project_resource_types', $data);
        $this->assertCount(1, $data['project_resource_types']);

        $typeName = $data['project_resource_types'][0]['name'];
        $this->assertEquals('Credentials', is_array($typeName) ? $typeName['en'] : $typeName);
        $this->assertEquals(['fields' => [['name' => 'api_key', 'type' => 'text']]], $data['project_resource_types'][0]['config']);
    }
}
