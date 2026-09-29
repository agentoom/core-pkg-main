<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Livewire\Admin\UserIndex;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\User;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

class ProjectSystemUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_artisan_command_creates_missing_system_users(): void
    {
        $user = User::factory()->create();

        // Create a project without triggering the 'created' event that creates a system user
        $projectId = DB::table('projects')->insertGetId([
            'name' => 'Legacy Project',
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $project = Project::find($projectId);
        $this->assertNull($project->system_user_id);

        Artisan::call('agentoom:create-missing-project-system-users');

        $project->refresh();
        $this->assertNotNull($project->system_user_id);

        $systemUser = User::find($project->system_user_id);
        $this->assertNotNull($systemUser);
        $this->assertEquals("project.{$project->id}.system@agentoom.internal", $systemUser->email);
        $this->assertTrue($systemUser->is_system);
    }

    public function test_system_user_cannot_be_deleted_manually(): void
    {
        $project = Project::factory()->create();
        $systemUser = $project->systemUser;

        $this->assertNotNull($systemUser);

        $deleted = $systemUser->delete();

        $this->assertFalse($deleted);
        $this->assertDatabaseHas('users', ['id' => $systemUser->id]);
    }

    public function test_system_user_is_deleted_when_project_is_deleted(): void
    {
        $project = Project::factory()->create();
        $systemUser = $project->systemUser;
        $systemUserId = $systemUser->id;

        $project->delete();

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        $this->assertDatabaseMissing('users', ['id' => $systemUserId]);
    }

    public function test_user_index_component_prevents_system_user_deletion(): void
    {
        $project = Project::factory()->create();
        $systemUser = $project->systemUser;

        Livewire::test(UserIndex::class)
            ->set('user_id_to_delete', $systemUser->id)
            ->call('delete');

        $this->assertDatabaseHas('users', ['id' => $systemUser->id]);
    }

    public function test_user_index_view_hides_delete_button_for_system_user(): void
    {
        $project = Project::factory()->create();
        $systemUser = $project->systemUser;

        Livewire::test(UserIndex::class)
            ->assertDontSee('showDeleteModal('.$systemUser->id.')');
    }
}
