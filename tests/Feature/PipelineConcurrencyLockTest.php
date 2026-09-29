<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineRun;
use Agentoom\Core\Models\PipelineStep;
use Agentoom\Core\Services\PipelineRunnerService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PipelineConcurrencyLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_allows_multiple_runs_when_lock_disabled(): void
    {
        $pipeline = Pipeline::create([
            'name' => 'Unlocked Pipeline',
            'concurrency_lock' => false,
        ]);
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => ConcurrencyStub::class,
            'method' => 'slowStep',
            'order' => 0,
            'instructions_override' => '[]',
        ]);

        $service = app(PipelineRunnerService::class);

        // Simulate a running run
        PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'status' => 'running',
            'input' => 'first',
        ]);

        $run = $service->run($pipeline, 'second');

        $this->assertNotEquals('failed', $run->status);
        $this->assertNull($run->error);
    }

    public function test_blocks_new_run_when_locked_and_active_run_exists(): void
    {
        $pipeline = Pipeline::create([
            'name' => 'Locked Pipeline',
            'concurrency_lock' => true,
        ]);
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => ConcurrencyStub::class,
            'method' => 'slowStep',
            'order' => 0,
            'instructions_override' => '[]',
        ]);

        $service = app(PipelineRunnerService::class);

        // Simulate a running run
        PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'status' => 'running',
            'input' => 'first',
        ]);

        $run = $service->run($pipeline, 'second');

        $this->assertEquals('failed', $run->status);
        $this->assertStringContainsString('Concurrency lock', $run->error);
    }

    public function test_blocks_new_run_when_locked_and_pending_run_exists(): void
    {
        $pipeline = Pipeline::create([
            'name' => 'Locked Pipeline Pending',
            'concurrency_lock' => true,
        ]);
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => ConcurrencyStub::class,
            'method' => 'slowStep',
            'order' => 0,
            'instructions_override' => '[]',
        ]);

        $service = app(PipelineRunnerService::class);

        PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'status' => 'pending',
            'input' => 'first',
        ]);

        $run = $service->run($pipeline, 'second');

        $this->assertEquals('failed', $run->status);
        $this->assertStringContainsString('Concurrency lock', $run->error);
    }

    public function test_allows_new_run_when_locked_but_previous_completed(): void
    {
        $pipeline = Pipeline::create([
            'name' => 'Locked but Completed',
            'concurrency_lock' => true,
        ]);
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => ConcurrencyStub::class,
            'method' => 'successStep',
            'order' => 0,
            'instructions_override' => '[]',
        ]);

        $service = app(PipelineRunnerService::class);

        PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'status' => 'completed',
            'input' => 'first',
            'output' => 'done',
            'completed_at' => now(),
        ]);

        $run = $service->run($pipeline, 'second');

        $this->assertEquals('completed', $run->status, 'Pipeline should complete when lock is enabled but no active runs exist');
    }

    public function test_allows_new_run_when_locked_but_previous_failed(): void
    {
        $pipeline = Pipeline::create([
            'name' => 'Locked but Failed',
            'concurrency_lock' => true,
        ]);
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => ConcurrencyStub::class,
            'method' => 'successStep',
            'order' => 0,
            'instructions_override' => '[]',
        ]);

        $service = app(PipelineRunnerService::class);

        PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'status' => 'failed',
            'input' => 'first',
            'error' => 'previous error',
            'completed_at' => now(),
        ]);

        $run = $service->run($pipeline, 'second');

        $this->assertEquals('completed', $run->status, 'Pipeline should run when previous run failed');
    }
}

class ConcurrencyStub
{
    public function slowStep(): string
    {
        usleep(50000);

        return 'slow done';
    }

    public function successStep(): string
    {
        return 'success';
    }
}
