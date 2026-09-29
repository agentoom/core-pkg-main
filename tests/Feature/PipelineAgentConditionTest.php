<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineStep;
use Agentoom\Core\Models\Provider;
use Agentoom\Core\Models\ProviderModel;
use Agentoom\Core\Services\AiCreditService;
use Agentoom\Core\Services\PipelineRunnerService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

class PipelineAgentConditionTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_step_condition_is_evaluated_after_execution_when_condition_on_current_is_true()
    {
        // Mock credits
        $creditService = \Mockery::mock(AiCreditService::class);
        $creditService->shouldReceive('hasSufficientCredits')->andReturn(true);
        $creditService->shouldReceive('logUsage')->andReturn(true);
        $creditService->shouldReceive('getExhaustedCreditsResponse')->andReturn([]);
        $this->app->instance(AiCreditService::class, $creditService);

        // Ensure roles exist to avoid Spatie errors if something checks them
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'superadmin', 'guard_name' => 'web']);

        // Setup provider and model
        $provider = Provider::create([
            'name' => 'openrouter',
            'api_key' => 'test-key',
            'url' => 'https://openrouter.ai/api/v1',
        ]);

        $model = ProviderModel::create([
            'provider_id' => $provider->id,
            'model_name' => 'gpt-4o',
            'model_features' => [],
        ]);

        // 1. Setup an agent and a pipeline
        $agent = Agent::factory()->create([
            'name' => 'Test Agent',
            'prompt_settings' => ['parts' => ['role' => true]],
            'config' => [
                'provider_id' => $provider->id,
                'provider_model_id' => $model->id,
            ],
        ]);

        $pipeline = Pipeline::create(['name' => 'Test Pipeline']);

        // Step 1: Agent step that returns "SUCCESS"
        // Step 2: Jump if "SUCCESS"

        $step1 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'agent_id' => $agent->id,
            'order' => 0,
            'condition_if' => 'SUCCESS',
            'next_step_on_true' => 2, // Jump to step 3
            'condition_on_previous' => false,
            'condition_on_input' => false,
            'condition_on_current' => true,
        ]);

        $step2 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 1,
            'name' => 'Should be skipped',
        ]);

        $step3 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 2,
            'name' => 'Target Step',
        ]);

        // Mock AI response for the agent
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'SUCCESS',
                        ],
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 10,
                    'completion_tokens' => 5,
                    'total_tokens' => 15,
                ],
            ]),
        ]);

        $runner = app(PipelineRunnerService::class);
        $run = $runner->run($pipeline, 'start', background: false);

        $run->load('stepRuns');

        // If the bug exists:
        // 1. executeRun starts Step 1.
        // 2. hasAction is false (because it only checks class/method).
        // 3. Pre-action condition check runs. currentOutput is null. input/previousOutput don't contain SUCCESS.
        // 4. conditionMet is false.
        // 5. nextOrder is next_step_on_false (which is null, so it continues to Step 1 execution).
        // 6. Step 1 executes, returns "SUCCESS".
        // 7. Post-action condition check does NOT run because hasAction is false.
        // 8. Pipeline continues to Step 2.

        // If fixed:
        // 1. hasAction is true.
        // 2. Pre-action check is skipped.
        // 3. Step 1 executes, returns "SUCCESS".
        // 4. Post-action check runs. conditionMet is true (evaluating on current output).
        // 5. nextOrder is 2.
        // 6. Pipeline jumps to Step 3.

        $orders = $run->stepRuns->pluck('order')->sort()->values()->toArray();

        // Expected if fixed: [0, 2]
        // Current behavior if bug exists: [0, 1, 2]

        $this->assertEquals([0, 2], $orders, 'Agent step condition should jump based on its own output');
    }
}
