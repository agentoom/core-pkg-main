<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Jobs\ProcessAgentKnowledgeJob;
use Agentoom\Core\Livewire\Admin\ProjectKnowledgeManager;
use Agentoom\Core\Models\AgentKnowledgeFile;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class ProjectKnowledgeManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
    }

    public function test_it_can_upload_knowledge_file_for_project()
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        $this->actingAs($user);

        $project = Project::factory()->create(['user_id' => $user->id]);

        Storage::fake('public');
        Queue::fake();

        $file = UploadedFile::fake()->create('knowledge.txt', 100);

        Livewire::test(ProjectKnowledgeManager::class, ['project' => $project])
            ->set('knowledgeInputs.0.file', $file)
            ->call('saveKnowledge');

        $this->assertDatabaseHas('agent_knowledge_files', [
            'project_id' => $project->id,
            'agent_id' => null,
            'name' => 'knowledge.txt',
            'type' => AgentKnowledgeFile::TYPE_FILE,
        ]);

        Queue::assertPushed(ProcessAgentKnowledgeJob::class);
    }

    public function test_it_can_add_knowledge_url_for_project()
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        $this->actingAs($user);

        $project = Project::factory()->create(['user_id' => $user->id]);

        Queue::fake();

        Livewire::test(ProjectKnowledgeManager::class, ['project' => $project])
            ->set('knowledgeInputs.0.type', 'url')
            ->set('knowledgeInputs.0.url', 'https://example.com/info')
            ->call('saveKnowledge');

        $this->assertDatabaseHas('agent_knowledge_files', [
            'project_id' => $project->id,
            'agent_id' => null,
            'path' => 'https://example.com/info',
            'type' => AgentKnowledgeFile::TYPE_URL,
        ]);

        Queue::assertPushed(ProcessAgentKnowledgeJob::class);
    }

    public function test_it_can_delete_knowledge_file()
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        $this->actingAs($user);

        $project = Project::factory()->create(['user_id' => $user->id]);
        $file = AgentKnowledgeFile::factory()->create([
            'project_id' => $project->id,
            'agent_id' => null,
        ]);

        Livewire::test(ProjectKnowledgeManager::class, ['project' => $project])
            ->call('confirmDeleteFile', $file->id)
            ->call('deleteFile');

        $this->assertDatabaseMissing('agent_knowledge_files', ['id' => $file->id]);
    }
}
