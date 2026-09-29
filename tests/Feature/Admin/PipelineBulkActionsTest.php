<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class PipelineBulkActionsTest extends TestCase
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

    public function test_it_can_delete_selected_steps(): void
    {
        $lw = Livewire::test('agentoom-core::admin.pipeline-create-edit')
            ->call('addStep')
            ->set('steps.0.name', 'Step A')
            ->call('addStep')
            ->set('steps.1.name', 'Step B')
            ->call('addStep')
            ->set('steps.2.name', 'Step C')
            ->call('addStep')
            ->set('steps.3.name', 'Step D');

        // Select Step B and Step D
        $uidB = $lw->get('steps.1.uid');
        $uidD = $lw->get('steps.3.uid');
        $lw->call('toggleStepSelection', $uidB)
            ->call('toggleStepSelection', $uidD);

        $lw->call('deleteSelectedSteps');

        $steps = $lw->get('steps');
        $this->assertCount(2, $steps);
        $this->assertEquals('Step A', $steps[0]['name']);
        $this->assertEquals('Step C', $steps[1]['name']);
        $this->assertEmpty($lw->get('selectedSteps'));
    }

    public function test_it_can_bulk_move_steps_before_another_step(): void
    {
        $lw = Livewire::test('agentoom-core::admin.pipeline-create-edit')
            ->call('addStep')
            ->set('steps.0.name', 'Step A')
            ->call('addStep')
            ->set('steps.1.name', 'Step B')
            ->call('addStep')
            ->set('steps.2.name', 'Step C')
            ->call('addStep')
            ->set('steps.3.name', 'Step D');

        // Select Step C and Step D (indices 2, 3)
        $uidC = $lw->get('steps.2.uid');
        $uidD = $lw->get('steps.3.uid');
        $lw->call('toggleStepSelection', $uidC)
            ->call('toggleStepSelection', $uidD);

        // Move them before Step B (index 1)
        $lw->call('openBulkMoveModal', false)
            ->set('targetStepIndex', 1)
            ->set('movePosition', 'before')
            ->call('executeMoveStep');

        $steps = $lw->get('steps');
        $this->assertEquals('Step A', $steps[0]['name']);
        $this->assertEquals('Step C', $steps[1]['name']);
        $this->assertEquals('Step D', $steps[2]['name']);
        $this->assertEquals('Step B', $steps[3]['name']);
    }

    public function test_it_can_bulk_move_steps_after_another_step(): void
    {
        $lw = Livewire::test('agentoom-core::admin.pipeline-create-edit')
            ->call('addStep')
            ->set('steps.0.name', 'Step A')
            ->call('addStep')
            ->set('steps.1.name', 'Step B')
            ->call('addStep')
            ->set('steps.2.name', 'Step C')
            ->call('addStep')
            ->set('steps.3.name', 'Step D');

        // Select Step A and Step B (indices 0, 1)
        $uidA = $lw->get('steps.0.uid');
        $uidB = $lw->get('steps.1.uid');
        $lw->call('toggleStepSelection', $uidA)
            ->call('toggleStepSelection', $uidB);

        // Move them after Step C (index 2)
        $lw->call('openBulkMoveModal', false)
            ->set('targetStepIndex', 2)
            ->set('movePosition', 'after')
            ->call('executeMoveStep');

        $steps = $lw->get('steps');
        $this->assertEquals('Step C', $steps[0]['name']);
        $this->assertEquals('Step A', $steps[1]['name']);
        $this->assertEquals('Step B', $steps[2]['name']);
        $this->assertEquals('Step D', $steps[3]['name']);
    }

    public function test_it_can_bulk_clone_and_move_steps(): void
    {
        $lw = Livewire::test('agentoom-core::admin.pipeline-create-edit')
            ->call('addStep')
            ->set('steps.0.name', 'Step A')
            ->call('addStep')
            ->set('steps.1.name', 'Step B');

        // Select Step A
        $uidA = $lw->get('steps.0.uid');
        $lw->call('toggleStepSelection', $uidA);

        // Clone and move after Step B
        $lw->call('openBulkMoveModal', true)
            ->set('targetStepIndex', 1)
            ->set('movePosition', 'after')
            ->call('executeMoveStep');

        $steps = $lw->get('steps');
        $this->assertCount(3, $steps);
        $this->assertEquals('Step A', $steps[0]['name']);
        $this->assertEquals('Step B', $steps[1]['name']);
        $this->assertEquals('Step A (Copy)', $steps[2]['name']);
    }

    public function test_it_can_select_all_steps(): void
    {
        $lw = Livewire::test('agentoom-core::admin.pipeline-create-edit')
            ->call('addStep')
            ->call('addStep')
            ->call('addStep');

        $lw->call('selectAllSteps');
        $this->assertCount(3, $lw->get('selectedSteps'));

        $lw->call('selectAllSteps');
        $this->assertCount(0, $lw->get('selectedSteps'));
    }
}
