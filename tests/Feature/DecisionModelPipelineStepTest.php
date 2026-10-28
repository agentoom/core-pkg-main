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
use Laravel\Ai\Ai;
use Laravel\Ai\AnonymousAgent;
use Spatie\Permission\Models\Role;

class DecisionModelPipelineStepTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'superadmin', 'guard_name' => 'web']);

        // Mock credits so runs are not blocked
        $creditService = \Mockery::mock(AiCreditService::class);
        $creditService->shouldReceive('hasSufficientCredits')->andReturn(true);
        $creditService->shouldReceive('logUsage')->andReturn(true);
        $creditService->shouldReceive('getExhaustedCreditsResponse')->andReturn([]);
        $this->app->instance(AiCreditService::class, $creditService);
    }

    public function test_pipeline_decision_step_branches_to_next_step_on_true_when_confidence_meets_threshold(): void
    {
        $provider = Provider::create([
            'name' => 'openrouter',
            'api_key' => 'sk-openrouter-test',
            'url' => 'https://openrouter.ai/api/v1',
        ]);

        $model = ProviderModel::create([
            'provider_id' => $provider->id,
            'model_name' => 'typesafe/jev-1.14',
            'model_features' => [
                'architecture' => ['output_modalities' => ['decisions']],
            ],
            'is_active' => true,
        ]);

        $pipeline = Pipeline::create(['name' => 'Decision Test Pipeline']);

        // Step 0: Decision Step -> jumps to step 2 on true, or step 1 on false
        $step0 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 0,
            'step_type' => PipelineStep::TYPE_DECISION,
            'next_step_on_true' => 2,
            'next_step_on_false' => 1,
            'decision_config' => [
                'provider_id' => $provider->id,
                'model' => $model->model_name,
                'instructions' => 'Does this input indicate high urgency?',
                'confidence_threshold' => 0.80,
                'question_type' => 'noul',
            ],
        ]);

        // Step 1: Should be skipped when decision is true
        $step1 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 1,
            'name' => 'Step 1 - Skipped',
        ]);

        // Step 2: Target step for true
        $step2 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 2,
            'name' => 'Step 2 - Target Success',
        ]);

        // Mock OpenRouter Decisions API
        Http::fake([
            '*/api/alpha/decisions' => Http::response([
                'answers' => [
                    'decision' => [
                        'type' => 'noul',
                        'noul' => 0.95,
                    ],
                ],
                'usage' => [
                    'prompt_tokens' => 12,
                    'completion_tokens' => 1,
                    'total_tokens' => 13,
                ],
            ], 200),
        ]);

        $runner = app(PipelineRunnerService::class);
        $run = $runner->run($pipeline, 'This is an urgent server outage!', background: false);

        $run->load('stepRuns');
        $executedOrders = $run->stepRuns->pluck('order')->sort()->values()->toArray();

        // Step 0 ran -> passed (0.95 >= 0.80) -> jumped straight to step 2
        $this->assertEquals([0, 2], $executedOrders);

        $step0Run = $run->stepRuns->firstWhere('order', 0);
        $this->assertEquals('completed', $step0Run->status);
        $decodedOutput = json_decode($step0Run->output, true);
        $this->assertTrue($decodedOutput['passed']);
        $this->assertEquals(0.95, $decodedOutput['confidence']);
    }

    public function test_pipeline_decision_step_branches_to_next_step_on_false_when_confidence_below_threshold(): void
    {
        $provider = Provider::create([
            'name' => 'openrouter',
            'api_key' => 'sk-openrouter-test',
            'url' => 'https://openrouter.ai/api/v1',
        ]);

        $model = ProviderModel::create([
            'provider_id' => $provider->id,
            'model_name' => 'typesafe/jev-1.14',
            'model_features' => [
                'architecture' => ['output_modalities' => ['decisions']],
            ],
            'is_active' => true,
        ]);

        $pipeline = Pipeline::create(['name' => 'Decision Test Pipeline False']);

        // Step 0: Decision Step -> jumps to step 2 on true, or step 1 on false
        $step0 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 0,
            'step_type' => PipelineStep::TYPE_DECISION,
            'next_step_on_true' => 2,
            'next_step_on_false' => 1,
            'decision_config' => [
                'provider_id' => $provider->id,
                'model' => $model->model_name,
                'instructions' => 'Does this input indicate high urgency?',
                'confidence_threshold' => 0.80,
                'question_type' => 'noul',
            ],
        ]);

        // Step 1: Fallback path when false
        $step1 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 1,
            'name' => 'Step 1 - Fallback Route',
        ]);

        // Step 2: Step 2
        $step2 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 2,
            'name' => 'Step 2 - Success Target',
        ]);

        // Mock OpenRouter Decisions API returning low confidence
        Http::fake([
            '*/api/alpha/decisions' => Http::response([
                'answers' => [
                    'decision' => [
                        'type' => 'noul',
                        'noul' => 0.45,
                    ],
                ],
            ], 200),
        ]);

        $runner = app(PipelineRunnerService::class);
        $run = $runner->run($pipeline, 'Routine question about pricing', background: false);

        $run->load('stepRuns');
        $executedOrders = $run->stepRuns->pluck('order')->sort()->values()->toArray();

        // Step 0 ran -> failed (0.45 < 0.80) -> jumped to step 1 -> then proceeded to step 2
        $this->assertEquals([0, 1, 2], $executedOrders);

        $step0Run = $run->stepRuns->firstWhere('order', 0);
        $this->assertEquals('completed', $step0Run->status);
        $decodedOutput = json_decode($step0Run->output, true);
        $this->assertFalse($decodedOutput['passed']);
        $this->assertEquals(0.45, $decodedOutput['confidence']);
    }

    public function test_pipeline_decision_step_works_with_universal_llm_fallback(): void
    {
        $provider = Provider::create([
            'name' => 'openai',
            'driver' => 'openai',
            'api_key' => 'sk-openai-test',
        ]);

        $model = ProviderModel::create([
            'provider_id' => $provider->id,
            'model_name' => 'gpt-4o-mini',
            'model_features' => [],
            'is_active' => true,
        ]);

        $pipeline = Pipeline::create(['name' => 'Decision Universal LLM Pipeline']);

        $step0 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 0,
            'step_type' => PipelineStep::TYPE_DECISION,
            'next_step_on_true' => 2,
            'next_step_on_false' => 1,
            'decision_config' => [
                'provider_id' => $provider->id,
                'model' => $model->model_name,
                'instructions' => 'Is this a valid bug report?',
                'confidence_threshold' => 0.75,
            ],
        ]);

        $step1 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 1,
            'name' => 'Step 1 - Skipped',
        ]);

        $step2 = PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 2,
            'name' => 'Step 2 - Target Success',
        ]);

        // Fake the AnonymousAgent prompt response with valid JSON
        Ai::fakeAgent(AnonymousAgent::class, [
            json_encode([
                'decision' => true,
                'confidence' => 0.92,
                'reasoning' => 'The input clearly describes an application crash.',
            ]),
        ]);

        $runner = app(PipelineRunnerService::class);
        $run = $runner->run($pipeline, 'App crashes on checkout button click', background: false);

        $run->load('stepRuns');
        $executedOrders = $run->stepRuns->pluck('order')->sort()->values()->toArray();

        $this->assertEquals([0, 2], $executedOrders);

        $step0Run = $run->stepRuns->firstWhere('order', 0);
        $decodedOutput = json_decode($step0Run->output, true);
        $this->assertTrue($decodedOutput['passed']);
        $this->assertEquals(0.92, $decodedOutput['confidence']);
        $this->assertEquals('openai', $decodedOutput['provider']);
    }
}
