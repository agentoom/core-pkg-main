<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineStep;
use Agentoom\Core\Services\PipelineRunnerService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PipelineFailureHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_failure_handler_executes_when_pipeline_fails(): void
    {
        $pipeline = Pipeline::create(['name' => 'Failure Handler Test']);
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => FailureTestStub::class,
            'method' => 'failStep',
            'order' => 0,
            'on_error' => 'fail',
            'instructions_override' => '[]',
        ]);
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => FailureTestStub::class,
            'method' => 'failureHandler',
            'order' => 9999,
            'is_failure_handler' => true,
            'instructions_override' => json_encode(['param1' => 'error_context']),
        ]);

        $service = app(PipelineRunnerService::class);
        $run = $service->run($pipeline, 'trigger failure');

        $run->refresh();
        $this->assertEquals('failed', $run->status);
        $this->assertStringContainsString('FailureTestStub triggered failure', $run->error);
        $this->assertStringContainsString('Failure handler executed', $run->error);
        $this->assertStringContainsString('error_context', $run->output);
    }

    public function test_failure_handler_skipped_on_success(): void
    {
        $pipeline = Pipeline::create(['name' => 'No Failure Test']);
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => FailureTestStub::class,
            'method' => 'successStep',
            'order' => 0,
            'instructions_override' => '[]',
        ]);
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => FailureTestStub::class,
            'method' => 'failureHandler',
            'order' => 9999,
            'is_failure_handler' => true,
            'instructions_override' => json_encode(['param1' => 'should_not_run']),
        ]);

        $service = app(PipelineRunnerService::class);
        $run = $service->run($pipeline, 'success');

        $run->refresh();
        $this->assertEquals('completed', $run->status);
        $this->assertEquals('Success', $run->output);
        $this->assertNull($run->error);
    }

    public function test_failure_handler_skipped_when_inactive(): void
    {
        $pipeline = Pipeline::create(['name' => 'Inactive Failure Handler']);
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => FailureTestStub::class,
            'method' => 'failStep',
            'order' => 0,
            'on_error' => 'fail',
            'instructions_override' => '[]',
        ]);
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => FailureTestStub::class,
            'method' => 'failureHandler',
            'order' => 9999,
            'is_failure_handler' => true,
            'is_active' => false,
            'instructions_override' => json_encode(['param1' => 'should_not_run']),
        ]);

        $service = app(PipelineRunnerService::class);
        $run = $service->run($pipeline, 'trigger failure');

        $run->refresh();
        $this->assertEquals('failed', $run->status);
        $this->assertStringContainsString('FailureTestStub', $run->error);
        $this->assertStringNotContainsString('Failure handler executed', $run->error);
    }

    public function test_failure_handler_not_triggered_by_caught_step_error(): void
    {
        $pipeline = Pipeline::create(['name' => 'Caught Error Test']);
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => FailureTestStub::class,
            'method' => 'failStep',
            'order' => 0,
            'on_error' => 'continue',
            'instructions_override' => '[]',
        ]);
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => FailureTestStub::class,
            'method' => 'successStep',
            'order' => 1,
            'instructions_override' => '[]',
        ]);
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => FailureTestStub::class,
            'method' => 'failureHandler',
            'order' => 9999,
            'is_failure_handler' => true,
            'instructions_override' => json_encode(['param1' => 'should_not_run']),
        ]);

        $service = app(PipelineRunnerService::class);
        $run = $service->run($pipeline, 'test');

        $run->refresh();
        $this->assertEquals('completed', $run->status, 'Pipeline should complete when error is caught');
        $this->assertNull($run->error);
    }

    public function test_failure_handler_failure_does_not_crash_pipeline(): void
    {
        $pipeline = Pipeline::create(['name' => 'Handler Also Fails']);
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => FailureTestStub::class,
            'method' => 'failStep',
            'order' => 0,
            'on_error' => 'fail',
            'instructions_override' => '[]',
        ]);
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => FailureTestStub::class,
            'method' => 'alsoFails',
            'order' => 9999,
            'is_failure_handler' => true,
            'instructions_override' => '[]',
        ]);

        $service = app(PipelineRunnerService::class);
        $run = $service->run($pipeline, 'trigger failure');

        $run->refresh();
        $this->assertEquals('failed', $run->status);
        $this->assertStringContainsString('FailureTestStub triggered failure', $run->error);
    }

    public function test_failure_handler_executes_agent_step(): void
    {
        $pipeline = Pipeline::create(['name' => 'Agent Failure Handler']);
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => FailureTestStub::class,
            'method' => 'failStep',
            'order' => 0,
            'on_error' => 'fail',
            'instructions_override' => '[]',
        ]);
        // A pass-through failure handler that just passes the input through
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 9999,
            'is_failure_handler' => true,
            'name' => 'Notify on Failure',
        ]);

        $service = app(PipelineRunnerService::class);
        $run = $service->run($pipeline, 'trigger failure');

        $run->refresh();
        $this->assertEquals('failed', $run->status);
        $this->assertStringContainsString('Failure handler executed: Notify on Failure', $run->error);
    }
}

class FailureTestStub
{
    public static $lastParam;

    public function failStep(): never
    {
        throw new \RuntimeException('FailureTestStub triggered failure');
    }

    public function successStep(): string
    {
        return 'Success';
    }

    public function failureHandler(string $param1 = ''): string
    {
        self::$lastParam = $param1;

        return "Failure handler executed with: {$param1}";
    }

    public function alsoFails(): never
    {
        throw new \RuntimeException('Failure handler also crashed');
    }
}
