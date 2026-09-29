<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Provider;
use Agentoom\Core\Models\ProviderModel;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class OpenRouterCacheHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adds_cache_header_when_disabled_in_config(): void
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

        $dynamicAgent = new DynamicAgent($agent);
        $dynamicAgent->prompt('hello');

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'openrouter.ai') &&
                   $request->hasHeader('X-OpenRouter-Cache', 'false');
        });
    }

    public function test_it_does_not_add_cache_header_when_enabled_in_config(): void
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

        $dynamicAgent = new DynamicAgent($agent);
        $dynamicAgent->prompt('hello');

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'openrouter.ai') &&
                   ! $request->hasHeader('X-OpenRouter-Cache');
        });
    }
}
