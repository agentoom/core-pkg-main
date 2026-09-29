<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineStep;
use Agentoom\Core\Services\PipelineRunnerService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PipelineEndCurrentPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_ends_pipeline_with_custom_status(): void
    {
        $pipeline = Pipeline::create(['name' => 'End Pipeline Test']);

        // Step 1: Call endCurrentPipeline with 'failed' status
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => PipelineRunnerService::class,
            'method' => 'endCurrentPipeline',
            'order' => 1,
            'instructions_override' => json_encode(['status' => 'failed', 'reason' => 'Stopped by test']),
        ]);

        // Step 2: This step should not be executed. We use a non-existent class to ensure it would fail if called.
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => 'NonExistentClass',
            'method' => 'someMethod',
            'order' => 2,
        ]);

        $service = new PipelineRunnerService;
        $run = $service->run($pipeline, 'initial input');

        $this->assertEquals('failed', $run->status);
        $this->assertEquals('Stopped by test', $run->output);
        $this->assertCount(1, $run->stepRuns);
        $this->assertEquals('completed', $run->stepRuns->first()->status);
        $this->assertEquals('Stopped by test', $run->stepRuns->first()->output);
    }

    public function test_it_ends_pipeline_with_default_completed_status(): void
    {
        $pipeline = Pipeline::create(['name' => 'End Pipeline Test Default']);

        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => PipelineRunnerService::class,
            'method' => 'endCurrentPipeline',
            'order' => 1,
            'instructions_override' => json_encode(['status' => 'completed', 'reason' => 'Success reason']),
        ]);

        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => 'NonExistentClass',
            'method' => 'someMethod',
            'order' => 2,
        ]);

        $service = new PipelineRunnerService;
        $run = $service->run($pipeline, 'initial input');

        $this->assertEquals('completed', $run->status);
        $this->assertEquals('Success reason', $run->output);
        $this->assertCount(1, $run->stepRuns);
    }
}
