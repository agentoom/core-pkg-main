<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\ProjectResource;
use Agentoom\Core\Models\ProjectResourceType;
use Agentoom\Core\Services\ExportService;
use Agentoom\Core\Services\ImportService;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProjectImportExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['email' => 'superadmin@unusualdope.com']);
    }

    public function test_exporting_project_includes_dependencies(): void
    {
        // 1. Setup project and dependencies
        $project = Project::create([
            'name' => 'Project Alpha',
            'description' => 'Alpha Project',
            'user_id' => $this->user->id,
        ]);

        $agent = Agent::create([
            'name' => 'Alpha Agent',
            'slug' => 'alpha-agent',
            'project_id' => $project->id,
            'user_id' => $this->user->id,
        ]);

        $pipeline = Pipeline::create([
            'name' => 'Alpha Pipeline',
            'project_id' => $project->id,
            'user_id' => $this->user->id,
        ]);

        $type = ProjectResourceType::create([
            'name' => 'Alpha Type',
            'project_id' => $project->id,
            'config' => [['name' => 'field1', 'type' => 'string']],
        ]);

        $resource = ProjectResource::create([
            'name' => 'Alpha Resource',
            'project_id' => $project->id,
            'project_resource_type_id' => $type->id,
            'data' => ['field1' => 'value1'],
        ]);

        // 2. Export ONLY the project
        $exportService = app(ExportService::class);
        $data = $exportService->export(['projects' => [$project->id]]);

        // 3. Assert dependencies are included in the export data
        $this->assertArrayHasKey('projects', $data);
        $this->assertArrayHasKey('agents', $data);
        $this->assertArrayHasKey('pipelines', $data);
        $this->assertArrayHasKey('project_resource_types', $data);
        $this->assertArrayHasKey('project_resources', $data);

        $this->assertEquals('Project Alpha', $data['projects'][0]['name']);
        $this->assertEquals('Alpha Agent', $data['agents'][0]['name']);
        $this->assertEquals('Project Alpha', $data['agents'][0]['project_name']);
        $this->assertEquals('Alpha Pipeline', $data['pipelines'][0]['name']);
        $this->assertEquals('Project Alpha', $data['pipelines'][0]['project_name']);
        $this->assertEquals('Alpha Type', $data['project_resource_types'][0]['name']);
        $this->assertEquals('Alpha Resource', $data['project_resources'][0]['name']);
    }

    public function test_importing_project_dependencies_resolves_project_by_name(): void
    {
        // 1. Prepare data (as if exported from another system)
        $data = [
            'projects' => [
                ['name' => 'Project Beta', 'description' => 'Beta Project'],
            ],
            'agents' => [
                ['name' => 'Beta Agent', 'slug' => 'beta-agent', 'project_name' => 'Project Beta'],
            ],
            'pipelines' => [
                ['name' => 'Beta Pipeline', 'project_name' => 'Project Beta'],
            ],
        ];

        // 2. Import
        $importService = app(ImportService::class);
        $importService->import($data);

        // 3. Assert everything is imported and linked
        $this->assertDatabaseHas('projects', ['name' => 'Project Beta']);
        $project = Project::where('name', 'Project Beta')->first();

        $this->assertDatabaseHas('agents', [
            'name' => 'Beta Agent',
            'project_id' => $project->id,
        ]);

        $this->assertDatabaseHas('pipelines', [
            'name' => 'Beta Pipeline',
            'project_id' => $project->id,
        ]);
    }
}
