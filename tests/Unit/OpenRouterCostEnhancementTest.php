<?php

namespace Agentoom\Core\Tests\Unit;

use Agentoom\Core\Models\AiModelPricing;
use Agentoom\Core\Models\Provider;
use Agentoom\Core\Models\ProviderModel;
use Agentoom\Core\Services\AiCreditService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;

class OpenRouterCostEnhancementTest extends TestCase
{
    use RefreshDatabase;

    private function addProvider(): void
    {
        Provider::create(['name' => 'openrouter', 'driver' => 'openrouter', 'api_key' => 'fake', 'url' => 'https://openrouter.ai/api/v1']);
    }

    public function test_unknown_model_fails_closed(): void
    {
        $this->addProvider();
        $this->assertNull(app(AiCreditService::class)->resolveCost(new Usage(1_000_000, 1_000_000), new Meta('openrouter', 'unknown')));
    }

    public function test_missing_component_rate_fails_closed(): void
    {
        $this->addProvider();
        AiModelPricing::create(['provider' => 'openrouter', 'model' => 'partial', 'source' => 'manual', 'input_price_per_million' => 1, 'output_price_per_million' => null]);

        $this->assertNull(app(AiCreditService::class)->resolveCost(new Usage(1_000_000, 1_000_000), new Meta('openrouter', 'partial')));
    }

    public function test_all_token_components_are_accounted_for_without_unit_heuristics(): void
    {
        $this->addProvider();
        AiModelPricing::create([
            'provider' => 'openrouter', 'model' => 'full-featured-model', 'source' => 'manual',
            'input_price_per_million' => 1, 'output_price_per_million' => 2,
            'cache_read_price_per_million' => 0.5, 'cache_write_price_per_million' => 0.75,
            'reasoning_price_per_million' => 1.5, 'request_price' => 0.05,
        ]);

        $resolution = app(AiCreditService::class)->resolveCost(
            new Usage(1_000_000, 1_000_000, 1_000_000, 1_000_000, 1_000_000),
            new Meta('openrouter', 'full-featured-model')
        );

        $this->assertNotNull($resolution);
        $this->assertEquals(5.8, $resolution->providerCost);
        $this->assertEquals(7.25, $resolution->creditCost);
    }

    public function test_openrouter_suffix_normalization_does_not_strip_free_or_version_suffixes(): void
    {
        $this->addProvider();
        AiModelPricing::create(['provider' => 'openrouter', 'model' => 'model', 'source' => 'manual', 'input_price_per_million' => 1, 'output_price_per_million' => 1]);

        $this->assertNotNull(app(AiCreditService::class)->resolveCost(new Usage(1_000_000, 0), new Meta('openrouter', 'model:nitro')));
        $this->assertNull(app(AiCreditService::class)->resolveCost(new Usage(1_000_000, 0), new Meta('openrouter', 'model:free')));
    }

    public function test_provider_model_features_are_read_at_their_stored_per_million_scale(): void
    {
        $this->addProvider();
        $provider = Provider::where('name', 'openrouter')->firstOrFail();
        ProviderModel::create([
            'provider_id' => $provider->id,
            'model_name' => 'feature-priced-model',
            'is_active' => true,
            'model_features' => ['pricing' => ['prompt' => '6.0', 'completion' => '12.0']],
        ]);

        $resolution = app(AiCreditService::class)->resolveCost(
            new Usage(1_000_000, 1_000_000),
            new Meta('openrouter', 'feature-priced-model')
        );

        // $6/M + $12/M = $18 provider cost, charged with the 1.25x credit markup.
        $this->assertNotNull($resolution);
        $this->assertEquals(18.0, $resolution->providerCost);
        $this->assertEquals(22.5, $resolution->creditCost);
    }

    public function test_stale_provider_model_features_are_not_priced(): void
    {
        $this->addProvider();
        $provider = Provider::where('name', 'openrouter')->firstOrFail();
        $model = ProviderModel::create([
            'provider_id' => $provider->id,
            'model_name' => 'feature-priced-model',
            'is_active' => true,
            'model_features' => ['pricing' => ['prompt' => '6.0', 'completion' => '12.0']],
        ]);
        ProviderModel::whereKey($model->id)->update(['updated_at' => now()->subHours(7)]);

        $this->assertNull(app(AiCreditService::class)->resolveCost(
            new Usage(1_000_000, 1_000_000),
            new Meta('openrouter', 'feature-priced-model')
        ));
    }
}
