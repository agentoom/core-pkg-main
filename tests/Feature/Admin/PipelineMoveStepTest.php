<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class PipelineMoveStepTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_move_step_before_another_step(): void
    {
        $user = User::factory()->create();
        Role::where('name', 'superadmin')->exists() ?: Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $user->assignRole('superadmin');
        $this->actingAs($user);

        $lw = Livewire::test('agentoom-core::admin.pipeline-create-edit')
            ->call('addStep') // Step 1
            ->set('steps.0.name', 'Step A')
            ->call('addStep') // Step 2
            ->set('steps.1.name', 'Step B')
            ->call('addStep') // Step 3
            ->set('steps.2.name', 'Step C');

        // Move Step C (index 2) before Step B (index 1)
        $lw->call('openMoveModal', 2)
            ->set('targetStepIndex', 1)
            ->set('movePosition', 'before')
            ->call('executeMoveStep');

        $steps = $lw->get('steps');
        $this->assertEquals('Step A', $steps[0]['name']);
        $this->assertEquals('Step C', $steps[1]['name']);
        $this->assertEquals('Step B', $steps[2]['name']);
    }

    public function test_it_can_move_step_after_another_step(): void
    {
        $user = User::factory()->create();
        Role::where('name', 'superadmin')->exists() ?: Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $user->assignRole('superadmin');
        $this->actingAs($user);

        $lw = Livewire::test('agentoom-core::admin.pipeline-create-edit')
            ->call('addStep') // Step 1
            ->set('steps.0.name', 'Step A')
            ->call('addStep') // Step 2
            ->set('steps.1.name', 'Step B')
            ->call('addStep') // Step 3
            ->set('steps.2.name', 'Step C');

        // Move Step A (index 0) after Step B (index 1)
        $lw->call('openMoveModal', 0)
            ->set('targetStepIndex', 1)
            ->set('movePosition', 'after')
            ->call('executeMoveStep');

        $steps = $lw->get('steps');
        $this->assertEquals('Step B', $steps[0]['name']);
        $this->assertEquals('Step A', $steps[1]['name']);
        $this->assertEquals('Step C', $steps[2]['name']);
    }
}
