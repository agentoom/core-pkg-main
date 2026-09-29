<?php

namespace Agentoom\Core\Tests\Unit;

use Agentoom\Core\Models\AiModelPricing;
use Agentoom\Core\Models\Provider;
use Agentoom\Core\Models\ProviderModel;
use Agentoom\Core\Services\ProviderModelSyncService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class OpenRouterSyncPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_syncs_openrouter_pricing_to_model_pricing_table(): void
    {
        $provider = Provider::create([
            'name' => 'openrouter',
            'api_key' => 'sk-fake',
            'url' => 'https://openrouter.ai/api/v1',
        ]);

        Http::fake([
            'https://openrouter.ai/api/v1/models' => Http::response([
                'data' => [
                    [
                        'id' => 'openai/gpt-4',
                        'name' => 'GPT-4',
                        'pricing' => [
                            'prompt' => '0.00003',
                            'completion' => '0.00006',
                        ],
                    ],
                    [
                        'id' => 'google/palm-2-chat-bison',
                        'name' => 'PaLM 2 Chat',
                        'pricing' => [
                            'prompt' => '0.000001',
                            'completion' => '0.000002',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = app(ProviderModelSyncService::class);
        $result = $service->syncProvider($provider);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['synced_count']);

        // Check ProviderModel
        $this->assertDatabaseHas('provider_models', [
            'provider_id' => $provider->id,
            'model_name' => 'openai/gpt-4',
        ]);

        // Check AiModelPricing
        $this->assertDatabaseHas('ai_model_pricings', [
            'provider' => 'openrouter',
            'model' => 'openai/gpt-4',
            'input_price_per_million' => 30.0,
            'output_price_per_million' => 60.0,
        ]);

        $this->assertDatabaseHas('ai_model_pricings', [
            'provider' => 'openrouter',
            'model' => 'google/palm-2-chat-bison',
            'input_price_per_million' => 1.0,
            'output_price_per_million' => 2.0,
        ]);
    }

    public function test_it_normalizes_documented_per_token_component_fields(): void
    {
        $provider = Provider::create([
            'name' => 'openrouter', 'driver' => 'openrouter', 'api_key' => 'sk-fake', 'url' => 'https://openrouter.ai/api/v1',
        ]);

        Http::fake([
            'https://openrouter.ai/api/v1/models*' => Http::response([
                'data' => [[
                    'id' => 'complex-model',
                    'pricing' => [
                        'prompt' => '0.00003', 'completion' => '0.00006',
                        'input_cache_read' => '0.000003', 'request' => '0.01',
                    ],
                ]],
            ], 200),
        ]);

        $service = app(ProviderModelSyncService::class);
        $service->syncProvider($provider);

        $model = ProviderModel::where('model_name', 'complex-model')->first();
        $pricing = $model->model_features['pricing'];
        $this->assertEquals(30.0, $pricing['prompt']);
        $this->assertEquals(60.0, $pricing['completion']);
        $this->assertEquals(3.0, $pricing['input_cache_read']);
        $this->assertEquals(0.01, $pricing['request']);
        $this->assertDatabaseHas('ai_model_pricings', [
            'provider' => 'openrouter', 'model' => 'complex-model', 'source' => 'provider_native',
            'input_price_per_million' => 30.0, 'output_price_per_million' => 60.0,
        ]);
    }
}
