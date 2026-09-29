<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Livewire\Admin\ProjectResourceTypeCreateEdit;
use Agentoom\Core\Models\Language;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\ProjectResourceType;
use Agentoom\Core\Models\User;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class ProjectResourceTypeReorderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Language::factory()->create(['code' => 'en', 'is_active' => true]);
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
    }

    public function test_can_reorder_fields_in_project_resource_type()
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        $this->actingAs($user);

        $project = Project::factory()->create();

        $projectResourceType = ProjectResourceType::factory()->create([
            'project_id' => $project->id,
            'config' => [
                ['name' => 'field1', 'type' => 'text'],
                ['name' => 'field2', 'type' => 'text'],
                ['name' => 'field3', 'type' => 'text'],
            ],
        ]);

        Livewire::test(ProjectResourceTypeCreateEdit::class, ['projectResourceType' => $projectResourceType])
            ->assertSet('config.0.name', 'field1')
            ->assertSet('config.1.name', 'field2')
            ->assertSet('config.2.name', 'field3')
            ->call('moveFieldDown', 0)
            ->assertSet('config.0.name', 'field2')
            ->assertSet('config.1.name', 'field1')
            ->assertSet('config.2.name', 'field3')
            ->call('moveFieldUp', 2)
            ->assertSet('config.0.name', 'field2')
            ->assertSet('config.1.name', 'field3')
            ->assertSet('config.2.name', 'field1');
    }
}
