<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Models\User;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class PipelineStepReorderReferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        Role::where('name', 'superadmin')->exists() ?: Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $user->assignRole('superadmin');
        $this->actingAs($user);
    }

    public function test_it_updates_selected_outputs_when_steps_are_moved_up(): void
    {
        $lw = Livewire::test('agentoom-core::admin.pipeline-create-edit')
            ->call('addStep') // Step 0 (UID A)
            ->set('steps.0.name', 'Step A')
            ->call('addStep') // Step 1 (UID B)
            ->set('steps.1.name', 'Step B')
            ->call('addStep') // Step 2 (UID C)
            ->set('steps.2.name', 'Step C')
            ->set('steps.2.selected_outputs', [1]); // Step C selects Step B

        // Current order: A(0), B(1), C(2). C selects index 1 (B).

        // Move B(1) up to index 0.
        // New order: B(0), A(1), C(2).
        $lw->call('moveUp', 1);

        $steps = $lw->get('steps');
        $this->assertEquals('Step B', $steps[0]['name']);
        $this->assertEquals('Step A', $steps[1]['name']);
        $this->assertEquals('Step C', $steps[2]['name']);

        // Step C (index 2) should now select index 0 (Step B).
        $this->assertEquals([0], $steps[2]['selected_outputs']);
    }

    public function test_it_updates_selected_outputs_when_steps_are_moved_down(): void
    {
        $lw = Livewire::test('agentoom-core::admin.pipeline-create-edit')
            ->call('addStep') // Step 0 (A)
            ->set('steps.0.name', 'Step A')
            ->call('addStep') // Step 1 (B)
            ->set('steps.1.name', 'Step B')
            ->call('addStep') // Step 2 (C)
            ->set('steps.2.name', 'Step C')
            ->set('steps.2.selected_outputs', [0]); // Step C selects Step A

        // Current order: A(0), B(1), C(2). C selects index 0 (A).

        // Move A(0) down to index 1.
        // New order: B(0), A(1), C(2).
        $lw->call('moveDown', 0);

        $steps = $lw->get('steps');
        $this->assertEquals('Step B', $steps[0]['name']);
        $this->assertEquals('Step A', $steps[1]['name']);
        $this->assertEquals('Step C', $steps[2]['name']);

        // Step C (index 2) should now select index 1 (Step A).
        $this->assertEquals([1], $steps[2]['selected_outputs']);
    }

    public function test_it_removes_invalid_selected_outputs_when_step_is_moved_before_them(): void
    {
        $lw = Livewire::test('agentoom-core::admin.pipeline-create-edit')
            ->call('addStep') // Step 0 (A)
            ->set('steps.0.name', 'Step A')
            ->call('addStep') // Step 1 (B)
            ->set('steps.1.name', 'Step B')
            ->call('addStep') // Step 2 (C)
            ->set('steps.2.name', 'Step C')
            ->set('steps.2.selected_outputs', [0, 1]); // Step C selects A and B

        // Move C(2) to index 0 (using openMoveModal and executeMoveStep simulation)
        $lw->set('movingStepIndex', 2)
            ->set('targetStepIndex', 0)
            ->set('movePosition', 'before')
            ->call('executeMoveStep');

        $steps = $lw->get('steps');
        // New order: C(0), A(1), B(2).
        $this->assertEquals('Step C', $steps[0]['name']);

        // Step C is now at index 0, it cannot have any selected outputs from previous steps.
        $this->assertEmpty($steps[0]['selected_outputs']);
    }

    public function test_it_updates_conditional_logic_next_step_indices_on_reorder(): void
    {
        $lw = Livewire::test('agentoom-core::admin.pipeline-create-edit')
            ->call('addStep') // Step 0 (A)
            ->set('steps.0.name', 'Step A')
            ->call('addStep') // Step 1 (B)
            ->set('steps.1.name', 'Step B')
            ->call('addStep') // Step 2 (C)
            ->set('steps.2.name', 'Step C')
            ->set('steps.0.next_step_on_true', 2); // Step A -> Step C

        // Move C(2) to index 1.
        // New order: A(0), C(1), B(2).
        $lw->set('movingStepIndex', 2)
            ->set('targetStepIndex', 1)
            ->set('movePosition', 'before')
            ->call('executeMoveStep');

        $steps = $lw->get('steps');
        $this->assertEquals('Step C', $steps[1]['name']);

        // Step A (index 0) next_step_on_true was 2 (C), now C is index 1.
        $this->assertEquals(1, $steps[0]['next_step_on_true']);
    }
}
