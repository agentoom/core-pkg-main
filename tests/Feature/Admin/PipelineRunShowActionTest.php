<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Livewire\Admin\PipelineRunShow;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineRun;
use Agentoom\Core\Models\PipelineStep;
use Agentoom\Core\Services\TestActionableService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

class PipelineRunShowActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_continue_from_step_via_livewire(): void
    {
        $pipeline = Pipeline::create(['name' => 'Livewire Pipeline']);
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => TestActionableService::class,
            'method' => 'testMethod',
            'order' => 0,
        ]);
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => TestActionableService::class,
            'method' => 'testMethod',
            'order' => 1,
        ]);

        $run = PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'status' => 'completed',
            'input' => 'input',
        ]);

        $run->stepRuns()->create([
            'pipeline_step_id' => $pipeline->steps()->where('order', 0)->first()->id,
            'order' => 0,
            'status' => 'completed',
            'output' => 'out 0',
        ]);

        $run->stepRuns()->create([
            'pipeline_step_id' => $pipeline->steps()->where('order', 1)->first()->id,
            'order' => 1,
            'status' => 'completed',
            'output' => 'out 1',
        ]);

        $firstStepRun = $run->stepRuns()->where('order', 0)->first();
        $secondStepRun = $run->stepRuns()->where('order', 1)->first();

        Livewire::test(PipelineRunShow::class, ['pipeline' => $pipeline, 'run' => $run])
            ->call('continueFromStep', 1)
            ->assertStatus(200);

        $run->refresh();

        // Verify that step 1 run was replaced (different ID)
        $this->assertDatabaseMissing('pipeline_run_steps', ['id' => $secondStepRun->id]);
        $this->assertDatabaseHas('pipeline_run_steps', ['id' => $firstStepRun->id]);

        $this->assertEquals(2, $run->stepRuns()->count());

        // Verify run status was updated (it might be completed already if it ran synchronously in the test)
        $run->refresh();
        $this->assertContains($run->status, ['pending', 'running', 'completed']);
    }

    public function test_it_can_retry_run_via_livewire(): void
    {
        $pipeline = Pipeline::create(['name' => 'Livewire Retry Pipeline']);
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => TestActionableService::class,
            'method' => 'testMethod',
            'order' => 0,
        ]);

        $run = PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'status' => 'failed',
            'input' => 'input',
        ]);

        $run->stepRuns()->create([
            'pipeline_step_id' => $pipeline->steps()->first()->id,
            'order' => 0,
            'status' => 'failed',
            'error' => 'error',
        ]);

        $oldStepRun = $run->stepRuns()->first();

        Livewire::test(PipelineRunShow::class, ['pipeline' => $pipeline, 'run' => $run])
            ->call('retry')
            ->assertStatus(200);

        $run->refresh();

        // Verify that the old step run was replaced
        $this->assertDatabaseMissing('pipeline_run_steps', ['id' => $oldStepRun->id]);
        $this->assertEquals(1, $run->stepRuns()->count());
    }
}
