<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Jobs\RunPipelineJob;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineRun;
use Agentoom\Core\Models\PipelineStep;
use Agentoom\Core\Services\PipelineExecutionService;
use Agentoom\Core\Services\PipelineRunnerService;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

class PipelineHandOffTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_can_trigger_new_pipeline_and_complete_current_one(): void
    {
        Bus::fake();
        $user = User::factory()->create();
        $this->actingAs($user);

        // Target pipeline
        $targetPipeline = Pipeline::factory()->create(['name' => 'Target Pipeline']);

        // Current pipeline
        $currentPipeline = Pipeline::factory()->create(['name' => 'Current Pipeline']);

        // Step in current pipeline that calls the target pipeline
        PipelineStep::create([
            'pipeline_id' => $currentPipeline->id,
            'class' => HandOffStub::class,
            'method' => 'triggerHandOff',
            'order' => 1,
            'instructions_override' => json_encode([
                ['name' => 'target', 'value' => 'Target Pipeline', 'type' => 'string'],
            ]),
        ]);

        $run = PipelineRun::create([
            'pipeline_id' => $currentPipeline->id,
            'status' => 'pending',
            'user_id' => $user->id,
        ]);

        $service = app(PipelineRunnerService::class);
        $service->executeRun($run);

        $run->refresh();
        $this->assertEquals('completed', $run->status);
        $this->assertStringContainsString('Handing off to pipeline', $run->output);

        Bus::assertDispatched(RunPipelineJob::class, function ($job) use ($targetPipeline) {
            $run = PipelineRun::find($job->pipelineRunId);

            return $run->pipeline_id === $targetPipeline->id;
        });
    }
}

class HandOffStub
{
    public function triggerHandOff($target)
    {
        app(PipelineExecutionService::class)->runPipeline($target, ['data' => 'from_previous'], 'Hand-off requested');

        return 'triggered';
    }
}
