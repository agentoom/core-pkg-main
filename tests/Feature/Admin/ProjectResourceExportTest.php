<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Livewire\Admin\ProjectResourceExport;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\ProjectResource;
use Agentoom\Core\Models\ProjectResourceType;
use Agentoom\Core\Models\User;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class ProjectResourceExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Project $project;

    protected ProjectResourceType $projectResourceType;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->be($this->admin);

        $this->project = Project::factory()->create(['user_id' => $this->admin->id]);

        $this->projectResourceType = ProjectResourceType::create([
            'project_id' => $this->project->id,
            'name' => ['en' => 'Server'],
            'config' => [
                'fields' => [
                    ['name' => 'IP', 'type' => 'text', 'required' => true],
                    ['name' => 'Port', 'type' => 'number'],
                ],
            ],
        ]);

        ProjectResource::create([
            'name' => 'Server 1',
            'description' => 'First server',
            'project_resource_type_id' => $this->projectResourceType->id,
            'project_id' => $this->project->id,
            'data' => ['IP' => '192.168.1.1', 'Port' => '80'],
        ]);
    }

    public function test_it_can_export_project_resources_to_csv()
    {
        Livewire::test(ProjectResourceExport::class)
            ->set('project_resource_type_id', $this->projectResourceType->id)
            ->set('format', 'csv')
            ->call('export')
            ->assertFileDownloaded();
    }

    public function test_it_can_export_project_resources_to_xlsx()
    {
        Livewire::test(ProjectResourceExport::class)
            ->set('project_resource_type_id', $this->projectResourceType->id)
            ->set('format', 'xlsx')
            ->call('export')
            ->assertFileDownloaded();
    }

    public function test_it_can_export_project_resources_to_xls()
    {
        Livewire::test(ProjectResourceExport::class)
            ->set('project_resource_type_id', $this->projectResourceType->id)
            ->set('format', 'xls')
            ->call('export')
            ->assertFileDownloaded();
    }

    public function test_it_shows_error_if_no_project_resources_to_export()
    {
        $emptyType = ProjectResourceType::create([
            'project_id' => $this->project->id,
            'name' => ['en' => 'Empty Type'],
            'config' => ['fields' => []],
        ]);

        Livewire::test(ProjectResourceExport::class)
            ->set('project_resource_type_id', $emptyType->id)
            ->set('format', 'csv')
            ->call('export')
            ->assertHasErrors(['project_resource_type_id']);
    }
}
