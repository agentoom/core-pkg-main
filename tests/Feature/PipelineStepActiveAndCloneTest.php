<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Livewire\Admin\PipelineCreateEdit;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineRun;
use Agentoom\Core\Models\PipelineStep;
use Agentoom\Core\Models\User;
use Agentoom\Core\Services\PipelineRunnerService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

class PipelineStepActiveAndCloneTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_steps_are_skipped(): void
    {
        $user = User::factory()->create();
        $agent = Agent::factory()->create(['user_id' => $user->id]);
        $pipeline = Pipeline::factory()->create(['user_id' => $user->id]);

        // Step 1: Inactive
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'agent_id' => $agent->id,
            'order' => 0,
            'is_active' => false,
            'name' => 'Inactive Step',
        ]);

        // Step 2: Active (Custom method to avoid AI overhead)
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => SimpleStepClass::class,
            'method' => 'performTestMethod',
            'order' => 1,
            'is_active' => true,
            'name' => 'Active Step',
        ]);

        $run = PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'user_id' => $user->id,
            'status' => 'pending',
            'input' => 'test input',
        ]);

        $runner = app(PipelineRunnerService::class);
        $runner->executeRun($run, 'test input');

        $run = $run->fresh();
        $this->assertEquals('completed', $run->status, 'Run failed with error: '.$run->error);
        $this->assertEquals('Method Output', $run->output);

        // Verify only 1 step run was created (for the active step)
        $this->assertEquals(1, $run->stepRuns()->count());
        $this->assertEquals('Active Step', $run->stepRuns->first()->pipelineStep->name);
    }

    public function test_can_clone_step_in_livewire(): void
    {
        $user = User::factory()->create();
        $agent = Agent::factory()->create(['user_id' => $user->id]);
        $pipeline = Pipeline::factory()->create(['user_id' => $user->id]);

        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'agent_id' => $agent->id,
            'order' => 0,
            'name' => 'Original Step',
            'instructions_override' => 'Original Instructions',
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(PipelineCreateEdit::class, ['pipeline' => $pipeline])
            ->assertSet('steps.0.name', 'Original Step')
            ->call('cloneStep', 0)
            ->assertCount('steps', 2)
            ->assertSet('steps.1.name', 'Original Step (Copy)')
            ->assertSet('steps.1.instructions_override', function ($value) {
                return str_contains($value, 'Original Instructions');
            })
            ->assertSet('steps.1.id', null)
            ->assertSet('steps.0.order', 0)
            ->assertSet('steps.1.order', 1)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(2, $pipeline->steps()->count());
        $this->assertEquals('Original Step', $pipeline->steps()->where('order', 0)->first()->name);
        $this->assertEquals('Original Step (Copy)', $pipeline->steps()->where('order', 1)->first()->name);
    }

    public function test_can_toggle_step_active_in_livewire(): void
    {
        $user = User::factory()->create();
        $agent = Agent::factory()->create(['user_id' => $user->id]);
        $pipeline = Pipeline::factory()->create(['user_id' => $user->id]);

        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'agent_id' => $agent->id,
            'order' => 0,
            'name' => 'Step 1',
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(PipelineCreateEdit::class, ['pipeline' => $pipeline])
            ->set('steps.0.is_active', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse((bool) $pipeline->steps()->first()->is_active);
    }
}

class SimpleStepClass
{
    public function performTestMethod(?string $input = null): string
    {
        return 'Method Output';
    }
}
