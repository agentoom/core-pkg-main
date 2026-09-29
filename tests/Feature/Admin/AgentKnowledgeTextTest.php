<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Jobs\ProcessAgentKnowledgeJob;
use Agentoom\Core\Livewire\Admin\AgentKnowledgeManager;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AgentKnowledgeFile;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class AgentKnowledgeTextTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_add_text_knowledge()
    {
        Storage::fake('public');
        Queue::fake();

        Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        $this->actingAs($user);

        $agent = Agent::factory()->create();

        Livewire::test(AgentKnowledgeManager::class, ['agent' => $agent])
            ->assertSet('knowledgeInputs', [])
            ->call('addTextInput')
            ->set('knowledgeInputs.0.type', 'text')
            ->set('knowledgeInputs.0.text', 'This is some manual knowledge text.')
            ->call('saveKnowledge')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('agent_knowledge_files', [
            'agent_id' => $agent->id,
            'type' => AgentKnowledgeFile::TYPE_TEXT,
            'status' => AgentKnowledgeFile::STATUS_PENDING,
        ]);

        $file = AgentKnowledgeFile::where('agent_id', $agent->id)->where('type', AgentKnowledgeFile::TYPE_TEXT)->first();
        $this->assertNotNull($file);
        $this->assertStringContainsString('Manual Entry', $file->name);

        Storage::disk('public')->assertExists($file->path);
        $this->assertEquals('This is some manual knowledge text.', Storage::disk('public')->get($file->path));

        Queue::assertPushed(ProcessAgentKnowledgeJob::class, function ($job) use ($file) {
            return $job->fileId === $file->id;
        });
    }

    public function test_text_knowledge_validation()
    {
        Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        $this->actingAs($user);

        $agent = Agent::factory()->create();

        Livewire::test(AgentKnowledgeManager::class, ['agent' => $agent])
            ->call('addTextInput')
            ->set('knowledgeInputs.0.type', 'text')
            ->set('knowledgeInputs.0.text', '') // Empty text should fail
            ->call('saveKnowledge')
            ->assertHasErrors(['knowledgeInputs.0.text']);
    }
}
