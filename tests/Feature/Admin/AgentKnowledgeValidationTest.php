<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Livewire\Admin\AgentKnowledgeManager;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class AgentKnowledgeValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_failure_does_not_lose_input_ids()
    {
        Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        $this->actingAs($user);

        $agent = Agent::factory()->create();

        $component = Livewire::test(AgentKnowledgeManager::class, ['agent' => $agent])
            ->call('addUrlInput')
            ->set('knowledgeInputs.0.type', 'url')
            ->set('knowledgeInputs.0.url', 'invalid-url') // This should fail validation
            ->call('saveKnowledge')
            ->assertHasErrors(['knowledgeInputs.0.url']);

        // Check if id is still there
        $knowledgeInputs = $component->get('knowledgeInputs');
        $this->assertArrayHasKey('id', $knowledgeInputs[0], 'ID key should be preserved after validation failure');
    }

    public function test_adding_and_removing_inputs_preserves_ids()
    {
        Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        $this->actingAs($user);

        $agent = Agent::factory()->create();

        $component = Livewire::test(AgentKnowledgeManager::class, ['agent' => $agent])
            ->call('addFileInput')
            ->call('addFileInput')
            ->call('addUrlInput');

        $knowledgeInputs = $component->get('knowledgeInputs');
        $this->assertCount(3, $knowledgeInputs);
        $this->assertEquals(2, $knowledgeInputs[0]['id']);
        $this->assertEquals(3, $knowledgeInputs[1]['id']);
        $this->assertEquals(4, $knowledgeInputs[2]['id']);

        $component->call('removeInput', 1); // Remove the middle one

        $knowledgeInputs = $component->get('knowledgeInputs');
        $this->assertCount(2, $knowledgeInputs);
        $this->assertEquals(2, $knowledgeInputs[0]['id']);
        $this->assertEquals(4, $knowledgeInputs[1]['id']);

        // Rendering should not fail
        $component->assertSee('knowledge-input-2')
            ->assertSee('knowledge-input-4');
    }
}
