<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Agents\PipelineStructuredAgent;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineRun;
use Agentoom\Core\Models\PipelineStep;
use Agentoom\Core\Models\Provider;
use Agentoom\Core\Models\ProviderModel;
use Agentoom\Core\Services\AiCreditService;
use Agentoom\Core\Services\PipelineRunnerService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

class PipelineForceJsonTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_returns_empty_when_not_in_pipeline_context(): void
    {
        $agentModel = Agent::factory()->create();

        $agent = new PipelineStructuredAgent(agentModel: $agentModel);

        $schema = $agent->schema(new JsonSchemaTypeFactory);

        $this->assertEmpty($schema, 'Schema should be empty when not in pipeline context');
    }

    public function test_schema_returns_empty_when_force_json_is_false(): void
    {
        $agentModel = Agent::factory()->create();
        $pipeline = Pipeline::factory()->create();
        $run = PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'status' => 'pending',
            'input' => 'test',
        ]);

        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'agent_id' => $agentModel->id,
            'order' => 1,
            'force_json' => false,
            'instructions_override' => json_encode([
                ['name' => 'title', 'type' => 'string', 'description' => 'The title'],
            ]),
        ]);

        $agent = new PipelineStructuredAgent(
            agentModel: $agentModel,
            pipelineRunId: $run->id,
            pipelineStepOrder: 1
        );

        $schema = $agent->schema(new JsonSchemaTypeFactory);

        $this->assertEmpty($schema, 'Schema should be empty when force_json is false');
    }

    public function test_schema_returns_schema_when_force_json_is_true(): void
    {
        $agentModel = Agent::factory()->create();
        $pipeline = Pipeline::factory()->create();
        $run = PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'status' => 'pending',
            'input' => 'test',
        ]);

        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'agent_id' => $agentModel->id,
            'order' => 1,
            'force_json' => true,
            'instructions_override' => json_encode([
                ['name' => 'title', 'type' => 'string', 'description' => 'The title of the item'],
                ['name' => 'count', 'type' => 'integer', 'description' => 'The count of items'],
                ['name' => 'active', 'type' => 'boolean', 'description' => 'Whether the item is active'],
                ['name' => 'price', 'type' => 'number', 'description' => 'The price of the item'],
            ]),
        ]);

        $agent = new PipelineStructuredAgent(
            agentModel: $agentModel,
            pipelineRunId: $run->id,
            pipelineStepOrder: 1
        );

        $schema = $agent->schema(new JsonSchemaTypeFactory);

        $this->assertNotEmpty($schema, 'Schema should not be empty when force_json is true');
        $this->assertArrayHasKey('title', $schema);
        $this->assertArrayHasKey('count', $schema);
        $this->assertArrayHasKey('active', $schema);
        $this->assertArrayHasKey('price', $schema);
    }

    public function test_schema_returns_empty_when_instructions_override_is_empty(): void
    {
        $agentModel = Agent::factory()->create();
        $pipeline = Pipeline::factory()->create();
        $run = PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'status' => 'pending',
            'input' => 'test',
        ]);

        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'agent_id' => $agentModel->id,
            'order' => 1,
            'force_json' => true,
            'instructions_override' => '',
        ]);

        $agent = new PipelineStructuredAgent(
            agentModel: $agentModel,
            pipelineRunId: $run->id,
            pipelineStepOrder: 1
        );

        $schema = $agent->schema(new JsonSchemaTypeFactory);

        $this->assertEmpty($schema, 'Schema should be empty when instructions_override is empty');
    }

    public function test_schema_returns_empty_when_instructions_not_in_json_structure_format(): void
    {
        $agentModel = Agent::factory()->create();
        $pipeline = Pipeline::factory()->create();
        $run = PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'status' => 'pending',
            'input' => 'test',
        ]);

        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'agent_id' => $agentModel->id,
            'order' => 1,
            'force_json' => true,
            'instructions_override' => json_encode(['key' => 'value']),
        ]);

        $agent = new PipelineStructuredAgent(
            agentModel: $agentModel,
            pipelineRunId: $run->id,
            pipelineStepOrder: 1
        );

        $schema = $agent->schema(new JsonSchemaTypeFactory);

        $this->assertEmpty($schema, 'Schema should be empty when instructions_override is not in JsonStructureHelper format');
    }

    public function test_agent_step_with_force_json_runs_successfully(): void
    {
        $creditService = \Mockery::mock(AiCreditService::class);
        $creditService->shouldReceive('hasSufficientCredits')->andReturn(true);
        $creditService->shouldReceive('logUsage')->andReturn(true);
        $creditService->shouldReceive('getExhaustedCreditsResponse')->andReturn([]);
        $this->app->instance(AiCreditService::class, $creditService);

        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'superadmin', 'guard_name' => 'web']);

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

        $agent = Agent::factory()->create([
            'name' => 'Test Agent',
            'prompt_settings' => ['parts' => ['role' => true]],
            'config' => [
                'provider_id' => $provider->id,
                'provider_model_id' => $model->id,
            ],
        ]);

        $pipeline = Pipeline::create(['name' => 'Test Pipeline']);

        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'agent_id' => $agent->id,
            'order' => 0,
            'force_json' => true,
            'instructions_override' => json_encode([
                ['name' => 'title', 'type' => 'string', 'description' => 'The title'],
            ]),
        ]);

        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => '{"title":"Hello World"}',
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
        $run = $runner->run($pipeline, 'Test input');

        $run->load('stepRuns');
        $this->assertEquals('completed', $run->status, 'Run failed: '.($run->error ?? 'no error'));

        $stepRuns = $run->stepRuns;
        $this->assertCount(1, $stepRuns);
        $this->assertEquals('{"title":"Hello World"}', $stepRuns->first()->output);
    }

    public function test_agent_step_without_force_json_produces_normal_output(): void
    {
        $creditService = \Mockery::mock(AiCreditService::class);
        $creditService->shouldReceive('hasSufficientCredits')->andReturn(true);
        $creditService->shouldReceive('logUsage')->andReturn(true);
        $creditService->shouldReceive('getExhaustedCreditsResponse')->andReturn([]);
        $this->app->instance(AiCreditService::class, $creditService);

        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Role::create(['name' => 'superadmin', 'guard_name' => 'web']);

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

        $agent = Agent::factory()->create([
            'name' => 'Test Agent',
            'prompt_settings' => ['parts' => ['role' => true]],
            'config' => [
                'provider_id' => $provider->id,
                'provider_model_id' => $model->id,
            ],
        ]);

        $pipeline = Pipeline::create(['name' => 'Test Pipeline']);

        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'agent_id' => $agent->id,
            'order' => 0,
            'force_json' => false,
            'instructions_override' => 'Some instructions',
        ]);

        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Normal text response',
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
        $run = $runner->run($pipeline, 'Test input');

        $run->load('stepRuns');
        $this->assertEquals('completed', $run->status, 'Run failed: '.($run->error ?? 'no error'));

        $stepRuns = $run->stepRuns;
        $this->assertCount(1, $stepRuns);
        $this->assertEquals('Normal text response', $stepRuns->first()->output);
    }
}
