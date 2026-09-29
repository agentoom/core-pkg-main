<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineRun;
use Agentoom\Core\Models\PipelineStep;
use Agentoom\Core\Services\PipelineExecutionService;
use Agentoom\Core\Services\PipelineRunnerService;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class PipelineLoopOutputTest extends TestCase
{
    use RefreshDatabase;

    public function test_pipeline_loop_does_not_stack_outputs(): void
    {
        $user = User::factory()->create();
        foreach (['admin', 'superadmin'] as $role) {
            if (! Role::where('name', $role)->where('guard_name', 'web')->exists()) {
                Role::create(['name' => $role, 'guard_name' => 'web']);
            }
        }
        $user->assignRole('superadmin');
        $this->actingAs($user);

        Configuration::setValue('app_ai_credits', 10000);
        JumpActionStub::$runs = 0;

        $agent = Agent::factory()->create(['name' => 'Test Agent']);
        $pipeline = Pipeline::factory()->create(['name' => 'Loop Pipeline']);

        // Step 1: Agent step
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'agent_id' => $agent->id,
            'order' => 1,
        ]);

        // Step 2: Jump action
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'class' => JumpActionStub::class,
            'method' => 'jumpOnce',
            'order' => 2,
        ]);

        // Step 3: Agent step selecting output from Step 1
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'agent_id' => $agent->id,
            'order' => 3,
            'selected_outputs' => [1],
        ]);

        $run = PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'status' => 'pending',
            'input' => 'Start',
            'user_id' => $user->id,
        ]);

        // We expect Step 1 to be called twice, then Step 3 to be called once.
        DynamicAgent::fake(function ($prompt) {
            if (str_contains($prompt, 'Start')) {
                return 'Output from first run of Step 1';
            }
            if (str_contains($prompt, 'Looping back')) {
                return 'Output from second run of Step 1';
            }
            if (str_contains($prompt, 'Selected Previous Outputs')) {
                return 'Final output';
            }

            return 'Default response';
        });

        $service = app(PipelineRunnerService::class);
        $service->executeRun($run);

        $run->refresh();
        $this->assertEquals('completed', $run->status, 'Run failed with error: '.$run->error);

        // Check Step 3 input
        $step3Run = $run->stepRuns()->where('order', 3)->first();
        $this->assertNotNull($step3Run);

        // It should NOT contain "STEP 2" for the second output of Step 1
        // and it should ideally only contain the latest output of Step 1.

        $this->assertStringContainsString('[OUTPUT STEP 1]Output from second run of Step 1[/OUTPUT STEP 1]', $step3Run->input);
        $this->assertStringNotContainsString('[OUTPUT STEP 2]', $step3Run->input);

        // Also check that it doesn't contain the first run output if we only want the latest
        $this->assertStringNotContainsString('Output from first run of Step 1', $step3Run->input);
    }
}

class JumpActionStub
{
    public static int $runs = 0;

    public function jumpOnce()
    {
        self::$runs++;
        if (self::$runs === 1) {
            app(PipelineExecutionService::class)->jumpToStep(1, 'Looping back');

            return 'Jumping back...';
        }

        return 'Continuing...';
    }
}
