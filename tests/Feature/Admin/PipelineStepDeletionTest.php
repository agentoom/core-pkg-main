<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Models\User;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class PipelineStepDeletionTest extends TestCase
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

    public function test_it_updates_selected_outputs_when_a_step_is_deleted(): void
    {
        $lw = Livewire::test('agentoom-core::admin.pipeline-create-edit')
            ->call('addStep') // Step 0
            ->set('steps.0.name', 'Step A')
            ->call('addStep') // Step 1
            ->set('steps.1.name', 'Step B')
            ->call('addStep') // Step 2
            ->set('steps.2.name', 'Step C')
            ->set('steps.2.selected_outputs', [0, 1]); // Step C selects A and B

        // Verify initial state
        $this->assertEquals([0, 1], $lw->get('steps.2.selected_outputs'));

        // Delete Step 1 (Step B)
        $lw->call('removeStep', 1);

        $steps = $lw->get('steps');
        $this->assertCount(2, $steps);
        $this->assertEquals('Step A', $steps[0]['name']);
        $this->assertEquals('Step C', $steps[1]['name']);

        // Step C (now index 1) should only have Step A (index 0) selected.
        // Step B (index 1) was deleted, so it should be removed.
        $this->assertEquals([0], $steps[1]['selected_outputs']);
    }

    public function test_it_shifts_selected_outputs_indices_when_a_previous_step_is_deleted(): void
    {
        $lw = Livewire::test('agentoom-core::admin.pipeline-create-edit')
            ->call('addStep') // Step 0
            ->set('steps.0.name', 'Step A')
            ->call('addStep') // Step 1
            ->set('steps.1.name', 'Step B')
            ->call('addStep') // Step 2
            ->set('steps.2.name', 'Step C')
            ->set('steps.2.selected_outputs', [1]); // Step C selects Step B

        // Delete Step 0 (Step A)
        $lw->call('removeStep', 0);

        $steps = $lw->get('steps');
        $this->assertCount(2, $steps);
        $this->assertEquals('Step B', $steps[0]['name']);
        $this->assertEquals('Step C', $steps[1]['name']);

        // Step C (now index 1) selected Step B (was index 1, now index 0).
        // So selected_outputs should be [0].
        $this->assertEquals([0], $steps[1]['selected_outputs']);
    }

    public function test_it_updates_next_step_on_logic_when_a_step_is_deleted(): void
    {
        $lw = Livewire::test('agentoom-core::admin.pipeline-create-edit')
            ->call('addStep') // Step 0
            ->set('steps.0.name', 'Step A')
            ->call('addStep') // Step 1
            ->set('steps.1.name', 'Step B')
            ->call('addStep') // Step 2
            ->set('steps.2.name', 'Step C')
            ->set('steps.0.next_step_on_true', 2) // Step A -> Step C on true
            ->set('steps.0.next_step_on_false', 1); // Step A -> Step B on false

        // Delete Step 1 (Step B)
        $lw->call('removeStep', 1);

        $steps = $lw->get('steps');

        // Step A (index 0) next_step_on_true was 2 (Step C), now Step C is index 1.
        $this->assertEquals(1, $steps[0]['next_step_on_true']);

        // Step A (index 0) next_step_on_false was 1 (Step B), Step B is deleted, should be null.
        $this->assertNull($steps[0]['next_step_on_false']);
    }
}
