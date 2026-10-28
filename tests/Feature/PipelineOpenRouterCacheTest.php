<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AgentSkill;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineRun;
use Agentoom\Core\Models\PipelineStep;
use Agentoom\Core\Models\Provider;
use Agentoom\Core\Models\ProviderModel;
use Agentoom\Core\Services\PipelineRunnerService;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class PipelineOpenRouterCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_pipeline_step_adds_cache_header_when_agent_disables_it(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'id' => 'gen-123',
                'model' => 'test-model',
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'hello']]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
            ]),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);
        Configuration::setValue('app_ai_credits', 10000);

        $provider = Provider::create([
            'name' => 'openrouter',
            'api_key' => 'test-key',
            'url' => 'https://openrouter.ai/api/v1',
        ]);

        $model = ProviderModel::create([
            'provider_id' => $provider->id,
            'model_name' => 'test-model',
            'is_active' => true,
            'model_features' => ['text'],
        ]);

        $agent = Agent::create([
            'name' => 'Test Agent',
            'user_id' => $user->id,
            'config' => [
                'provider_id' => $provider->id,
                'provider_model_id' => $model->id,
                'openrouter_disable_cache' => true,
            ],
        ]);

        // Add a skill to trigger routing
        $skill = AgentSkill::create([
            'name' => 'Test Skill',
            'slug' => 'test-skill',
            'instructions' => 'Use this skill',
            'project_id' => $agent->project_id,
        ]);
        $agent->skills()->attach($skill->id);

        $pipeline = Pipeline::factory()->create(['name' => 'Test Pipeline']);
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'agent_id' => $agent->id,
            'order' => 1,
        ]);

        $run = PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'status' => 'pending',
            'input' => 'Test input',
            'user_id' => $user->id,
        ]);

        $service = app(PipelineRunnerService::class);
        $service->executeRun($run);

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'openrouter.ai') &&
                   $request->hasHeader('X-OpenRouter-Cache', 'false');
        });
    }

    public function test_pipeline_step_does_not_add_cache_header_when_agent_enables_it(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'id' => 'gen-124',
                'model' => 'test-model',
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'hello']]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
            ]),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);
        Configuration::setValue('app_ai_credits', 10000);

        $provider = Provider::create([
            'name' => 'openrouter',
            'api_key' => 'test-key',
            'url' => 'https://openrouter.ai/api/v1',
        ]);

        $model = ProviderModel::create([
            'provider_id' => $provider->id,
            'model_name' => 'test-model',
            'is_active' => true,
            'model_features' => ['text'],
        ]);

        $agent = Agent::create([
            'name' => 'Test Agent',
            'user_id' => $user->id,
            'config' => [
                'provider_id' => $provider->id,
                'provider_model_id' => $model->id,
                'openrouter_disable_cache' => false,
            ],
        ]);

        $pipeline = Pipeline::factory()->create(['name' => 'Test Pipeline']);
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'agent_id' => $agent->id,
            'order' => 1,
        ]);

        $run = PipelineRun::create([
            'pipeline_id' => $pipeline->id,
            'status' => 'pending',
            'input' => 'Test input',
            'user_id' => $user->id,
        ]);

        $service = app(PipelineRunnerService::class);
        $service->executeRun($run);

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'openrouter.ai') &&
                   ! $request->hasHeader('X-OpenRouter-Cache');
        });
    }
}
