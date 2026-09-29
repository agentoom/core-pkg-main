<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Exceptions\StopPipelineException;
use Agentoom\Core\Jobs\RunPipelineJob;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineRun;
use Agentoom\Core\Models\PipelineStep;
use Agentoom\Core\Models\User;
use Agentoom\Core\Services\PipelineRunnerService;
use Agentoom\Core\Tests\TestCase;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Ai;
use Spatie\Permission\Models\Role;

class PipelineExecutionIssueTest extends TestCase
{
    use RefreshDatabase;

    public function test_resume_from_pause_prioritizes_paused_step_after_jump_back(): void
    {
        Log::spy();
        $user = User::factory()->create();
        $pipeline = Pipeline::factory()->create(['user_id' => $user->id]);
        $agent = Agent::factory()->create(['user_id' => $user->id]);

        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'agent_id' => $agent->id,
            'order' => 5,
            'class' => 'stdClass',
            'method' => 'noop',
            'is_active' => true,
        ]);

        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'agent_id' => $agent->id,
            'order' => 6,
            'class' => 'stdClass',
            'method' => 'noop',
            'is_active' => true,
        ]);

        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'agent_id' => $agent->id,
            'order' => 7,
            'class' => 'stdClass',
            'method' => 'noop',
            'is_active' => true,
        ]);

        $run = PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'user_id' => $user->id,
            'status' => 'paused',
            'input' => 'resume-test',
            'paused_at' => now()->subMinute(),
        ]);

        $run->stepRuns()->create([
            'pipeline_step_id' => $pipeline->steps()->where('order', 6)->firstOrFail()->id,
            'order' => 6,
            'status' => 'completed',
            'output' => 'Jumping to step 5',
            'started_at' => now()->subMinutes(2),
            'completed_at' => now()->subMinutes(2),
        ]);

        $run->stepRuns()->create([
            'pipeline_step_id' => $pipeline->steps()->where('order', 5)->firstOrFail()->id,
            'order' => 5,
            'status' => 'paused',
            'output' => 'Execution paused until later',
            'started_at' => now()->subMinute(),
            'completed_at' => now()->subMinute(),
        ]);

        $runner = $this->getMockBuilder(PipelineRunnerService::class)
            ->onlyMethods(['runStep'])
            ->getMock();

        $executedOrders = [];
        $runner->method('runStep')
            ->willReturnCallback(function (PipelineRun $runArg, PipelineStep $stepArg, ?string $originalInput, ?string $previousOutput, ?int $scheduleId, ?int $scheduleLogId, ?int $aiTaskId, ?int $endpointId, ?int $endpointLogId, bool $isResumingFromPause) use (&$executedOrders) {
                $executedOrders[] = $stepArg->order;

                return "step {$stepArg->order} output";
            });

        $runner->executeRun($run, $run->input);

        $this->assertSame([5, 6, 7], $executedOrders);
    }

    public function test_run_pipeline_job_handles_deleted_run_gracefully(): void
    {
        Log::spy();
        $user = User::factory()->create();
        $pipeline = Pipeline::factory()->create(['user_id' => $user->id]);
        $run = PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'user_id' => $user->id,
            'status' => 'running',
            'input' => 'test',
        ]);

        // We want to simulate the run being deleted during executeRun
        $mockRunner = $this->mock(PipelineRunnerService::class, function ($mock) use ($run) {
            $mock->shouldReceive('executeRun')->once()->andReturnUsing(function () use ($run) {
                $run->delete();
                throw new Exception('Simulated failure after deletion');
            });
        });

        $job = new RunPipelineJob($run->id);

        // This should not throw "Attempt to read property 'status' on null"
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Simulated failure after deletion');

        $job->handle($mockRunner);
    }

    public function test_pipeline_stops_when_tool_requests_stop(): void
    {
        Log::spy();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        Configuration::setValue('app_ai_credits', 10000);
        // This test checks if the pipeline actually stops when the tool is called.
        // We'll use the real PipelineRunnerService but mock the AI agent.

        $user = User::factory()->create();
        $agentModel = Agent::factory()->create(['user_id' => $user->id]);
        $pipeline = Pipeline::factory()->create(['user_id' => $user->id]);

        // Step 1 calls the tool to stop
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'agent_id' => $agentModel->id,
            'order' => 1,
        ]);

        // Step 2 should never be reached
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'agent_id' => $agentModel->id,
            'order' => 2,
        ]);

        $run = PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'user_id' => $user->id,
            'status' => 'running',
            'input' => 'test',
        ]);

        // Mock the AI to call the PipelineExecutionTool with "stop"
        // Note: In real life, the LLM decides to call the tool.
        // Here we simulate the agent's behavior.
        Ai::fakeAgent(DynamicAgent::class, function () {
            throw new StopPipelineException('Agent wants to stop');
        });

        $runner = app(PipelineRunnerService::class);
        $runner->executeRun($run, 'test');

        $run = $run->fresh();
        $this->assertEquals('stopped', $run->status, 'Run failed with error: '.$run->error);
        $this->assertEquals('Agent wants to stop', $run->output);

        // Check that only 1 step was run (or rather, the second step was not completed as expected)
        $this->assertEquals(1, $run->stepRuns()->count());
    }
}
