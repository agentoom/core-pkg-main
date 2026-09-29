<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class AgentCreateEditValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Role::where('name', 'admin')->exists()) {
            Role::create(['name' => 'admin']);
        }
    }

    public function test_assignment_explanation_can_be_longer_than_255_characters()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $permittedAgent = Agent::create([
            'name' => 'Permitted Agent',
            'instructions' => 'You are a test agent.',
        ]);

        $longExplanation = str_repeat('a', 300);

        Livewire::actingAs($admin)
            ->test('agentoom-core::admin.agent-create-edit', ['agent' => null])
            ->set('name', 'Test Agent')
            ->set('instructions', 'Test Instructions')
            ->set('task_permissions', [
                [
                    'permitted_agent_id' => $permittedAgent->id,
                    'can_assign' => true,
                    'can_view' => true,
                    'assignment_explanation' => $longExplanation,
                ],
            ])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('agent_task_permissions', [
            'permitted_agent_id' => $permittedAgent->id,
            'assignment_explanation' => $longExplanation,
        ]);
    }
}
