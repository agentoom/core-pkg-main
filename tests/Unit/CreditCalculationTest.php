<?php

namespace Agentoom\Core\Tests\Unit;

use Agentoom\Core\Models\AiModelPricing;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\Provider;
use Agentoom\Core\Services\AiCreditService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;

class CreditCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_matches_only_documented_openrouter_suffixes(): void
    {
        Provider::create(['name' => 'openrouter', 'driver' => 'openrouter', 'api_key' => 'fake-key', 'url' => 'https://openrouter.ai/api/v1']);
        AiModelPricing::create([
            'provider' => 'openrouter', 'model' => 'google/gemini-flash', 'source' => 'manual',
            'input_price_per_million' => 0.1, 'output_price_per_million' => 0.1,
        ]);

        $resolution = app(AiCreditService::class)->resolveCost(
            new Usage(1_000_000, 0),
            new Meta('openrouter', 'google/gemini-flash:nitro')
        );

        $this->assertNotNull($resolution);
        $this->assertEqualsWithDelta(0.1, $resolution->providerCost, 0.000001);
        $this->assertEqualsWithDelta(0.125, $resolution->creditCost, 0.000001);
        $this->assertSame('manual', $resolution->source);
    }

    public function test_explicit_free_models_do_not_fall_back_to_defaults(): void
    {
        Provider::create(['name' => 'openrouter', 'driver' => 'openrouter', 'api_key' => 'fake-key', 'url' => 'https://openrouter.ai/api/v1']);
        AiModelPricing::create([
            'provider' => 'openrouter', 'model' => 'free-model', 'source' => 'manual',
            'input_price_per_million' => 0, 'output_price_per_million' => 0,
        ]);

        $resolution = app(AiCreditService::class)->resolveCost(
            new Usage(1_000_000, 1_000_000),
            new Meta('openrouter', 'free-model')
        );

        $this->assertNotNull($resolution);
        $this->assertSame(0.0, $resolution->providerCost);
        $this->assertSame(0.0, $resolution->creditCost);
    }

    public function test_generic_usd_usage_deducts_credits_atomically(): void
    {
        Configuration::setValue('app_ai_credits', 100.0);
        app(AiCreditService::class)->logGenericUsage(null, 'test_service_request', 0.15);

        $this->assertEquals(99.85, (float) Configuration::getValue('app_ai_credits'));
    }
}
