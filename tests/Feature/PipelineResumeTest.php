<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineStep;
use Agentoom\Core\Services\PipelineRunnerService;
use Agentoom\Core\Services\TestActionableService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PipelineResumeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_resume_pipeline_from_specific_step(): void
    {
        $pipeline = Pipeline::create(['name' => 'Resume Pipeline']);

        // Step 0
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => TestActionableService::class,
            'method' => 'testMethod',
            'order' => 0,
        ]);

        // Step 1
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => TestActionableService::class,
            'method' => 'testMethod',
            'order' => 1,
        ]);

        $service = new PipelineRunnerService;

        // Initial run
        $run = $service->run($pipeline, 'initial input');
        $this->assertEquals('completed', $run->status);
        $this->assertCount(2, $run->stepRuns);

        $firstStepRunId = $run->stepRuns()->where('order', 0)->first()->id;
        $secondStepRunId = $run->stepRuns()->where('order', 1)->first()->id;

        // Resume from step 1 (order 1)
        $run = $service->resumeFromStep($run, 1);

        $this->assertEquals('completed', $run->status);

        // Verify that old step 1 run was deleted
        $this->assertDatabaseMissing('pipeline_run_steps', ['id' => $secondStepRunId]);
        // Verify that step 0 run remained
        $this->assertDatabaseHas('pipeline_run_steps', ['id' => $firstStepRunId]);

        // Verify we have a new step 1 run
        $newSecondStepRun = $run->stepRuns()->where('order', 1)->first();
        $this->assertNotNull($newSecondStepRun);
        $this->assertNotEquals($secondStepRunId, $newSecondStepRun->id);
    }

    public function test_it_can_resume_from_the_beginning(): void
    {
        $pipeline = Pipeline::create(['name' => 'Resume from Start Pipeline']);

        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => TestActionableService::class,
            'method' => 'testMethod',
            'order' => 0,
        ]);

        $service = new PipelineRunnerService;
        $run = $service->run($pipeline, 'initial input');
        $oldStepRunId = $run->stepRuns()->first()->id;

        // Resume from step 0
        $run = $service->resumeFromStep($run, 0);

        $this->assertDatabaseMissing('pipeline_run_steps', ['id' => $oldStepRunId]);
        $this->assertCount(1, $run->stepRuns);
        $this->assertNotEquals($oldStepRunId, $run->stepRuns()->first()->id);
    }
}
