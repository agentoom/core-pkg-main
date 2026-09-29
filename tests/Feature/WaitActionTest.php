<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Exceptions\ActionPausedException;
use Agentoom\Core\Execution\ActionExecution;
use Agentoom\Core\Jobs\ResumePipelineJob;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineRun;
use Agentoom\Core\Services\ActionDiscoveryService;
use Agentoom\Core\Services\WaitService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class WaitActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_wait_action_is_discovered(): void
    {
        $service = new ActionDiscoveryService;
        $methods = $service->discover();

        $key = 'Agentoom\Core\Services\WaitService@wait';

        $this->assertArrayHasKey($key, $methods);
        $this->assertEquals('Wait', $methods[$key]['name']);
    }

    public function test_wait_action_pauses_pipeline_run(): void
    {
        $pipeline = Pipeline::create(['name' => 'Wait Pipeline']);
        $run = PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'status' => 'running',
            'paused_at' => null,
            'resume_at' => null,
        ]);

        $agent = Agent::factory()->create();
        $dynamicAgent = new DynamicAgent($agent);
        $actionExecution = new ActionExecution(
            $dynamicAgent,
            new class implements Tool
            {
                public function description(): string
                {
                    return 'test';
                }

                public function handle(Request $request): string
                {
                    return 'ok';
                }

                public function schema(JsonSchema $schema): array
                {
                    return [];
                }
            },
            new Request([]),
            pipelineRunId: $run->id
        );
        app()->instance(ActionExecution::class, $actionExecution);

        $service = new WaitService;

        try {
            $service->wait('10 seconds');
            $this->fail('ActionPausedException was not thrown.');
        } catch (ActionPausedException $e) {
            $this->assertStringContainsString('Execution paused until', $e->getMessage());
        }

        $run->refresh();
        $this->assertEquals('paused', $run->status);
        $this->assertNotNull($run->paused_at);
        $this->assertNotNull($run->resume_at);
        $this->assertTrue($run->resume_at->isAfter(now()));
    }

    public function test_resume_waits_command_dispatches_resume_job(): void
    {
        Bus::fake();

        $pipeline = Pipeline::create(['name' => 'Wait Pipeline']);
        $run = PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'status' => 'paused',
            'paused_at' => now()->subMinutes(5),
            'resume_at' => now()->subSeconds(10), // Ready to resume
        ]);

        $this->artisan('agentoom:resume-waits')
            ->expectsOutput('Found 1 pipeline runs to resume.')
            ->expectsOutput("Resuming pipeline run #{$run->id} (Scheduled for: {$run->resume_at})...")
            ->assertExitCode(0);

        Bus::assertDispatched(ResumePipelineJob::class, function ($job) use ($run) {
            return $job->pipelineRunId === $run->id;
        });

        $run->refresh();
        $this->assertNull($run->resume_at);
    }
}
