<?php

namespace Agentoom\Core\Tests\Unit;
use Agentoom\Core\Models\AiModelPricing;
use Agentoom\Core\Services\AiPricingSyncService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

class AiPricingSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_litellm_token_prices_are_converted_and_partial_rows_are_skipped(): void
    {
        $url = 'https://prices.test/litellm.json';
        config(['ai-pricing.feeds.litellm.url' => $url]);
        Http::fake([$url => Http::response([
            'openai/gpt-4o' => [
                'litellm_provider' => 'openai',
                'input_cost_per_token' => 0.000001,
                'output_cost_per_token' => 0.000002,
            ],
            'openai/partial' => [
                'litellm_provider' => 'openai',
                'input_cost_per_token' => 0.000001,
            ],
        ], 200, ['ETag' => 'v1'])]);

        $result = app(AiPricingSyncService::class)->sync('litellm');

        $this->assertSame('success', $result['status']);
        $this->assertSame(1, $result['entry_count']);
        $this->assertDatabaseHas('ai_model_pricings', [
            'provider' => 'openai', 'model' => 'openai/gpt-4o', 'source' => 'litellm',
            'input_price_per_million' => 1.0, 'output_price_per_million' => 2.0,
        ]);
        $this->assertDatabaseMissing('ai_model_pricings', ['model' => 'openai/partial', 'source' => 'litellm']);
    }

    public function test_models_dev_prices_are_read_as_per_million_and_manual_rows_are_protected(): void
    {
        $url = 'https://prices.test/models.json';
        config(['ai-pricing.feeds.models_dev.url' => $url]);
        AiModelPricing::create(['provider' => 'openai', 'model' => 'gpt-4o', 'source' => 'manual', 'input_price_per_million' => 9, 'output_price_per_million' => 10]);
        Http::fake([$url => Http::response([
            'openai' => ['models' => ['gpt-4o' => ['cost' => ['input' => 1, 'output' => 2]]]],
        ], 200)]);

        $result = app(AiPricingSyncService::class)->sync('models_dev');

        $this->assertSame('success', $result['status']);
        $this->assertDatabaseHas('ai_model_pricings', ['provider' => 'openai', 'model' => 'gpt-4o', 'source' => 'models_dev', 'input_price_per_million' => 1, 'output_price_per_million' => 2]);
        $this->assertDatabaseHas('ai_model_pricings', ['provider' => 'openai', 'model' => 'gpt-4o', 'source' => 'manual', 'input_price_per_million' => 9]);
    }
    public function test_304_refreshes_freshness_without_rewriting_rows(): void
    {
        $url = 'https://prices.test/litellm.json';
        config(['ai-pricing.feeds.litellm.url' => $url]);
        Http::fakeSequence($url)
            ->push([
                'openai/gpt-4o' => ['litellm_provider' => 'openai', 'input_cost_per_token' => 0.000001, 'output_cost_per_token' => 0.000002],
            ], 200, ['ETag' => 'v1'])
            ->push('', 304, ['ETag' => 'v1']);
        $sync = app(AiPricingSyncService::class);
        $sync->sync('litellm');
        $pricedAt = AiModelPricing::where('source', 'litellm')->value('priced_at');

        $result = $sync->sync('litellm');

        $this->assertSame('not_modified', $result['status']);
        $this->assertEquals($pricedAt, AiModelPricing::where('source', 'litellm')->value('priced_at'));
        $this->assertNotNull(AiModelPricing::where('source', 'litellm')->value('last_seen_at'));
    }

    public function test_sync_command_runs_against_a_fake_feed(): void
    {
        $url = 'https://prices.test/command.json';
        config(['ai-pricing.feeds.litellm.url' => $url]);
        Http::fake([$url => Http::response([
            'openai/gpt-4o' => ['litellm_provider' => 'openai', 'input_cost_per_token' => 0.000001, 'output_cost_per_token' => 0.000002],
        ], 200)]);

        $exitCode = Artisan::call('ai:sync-pricing', ['source' => 'litellm']);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseHas('ai_model_pricings', ['source' => 'litellm', 'model' => 'openai/gpt-4o']);
    }

    public function test_failed_payload_keeps_previous_rows(): void
    {
        $url = 'https://prices.test/failure.json';
        config(['ai-pricing.feeds.litellm.url' => $url]);
        Http::fakeSequence($url)
            ->push([
                'm1' => ['litellm_provider' => 'openai', 'input_cost_per_token' => 0.000001, 'output_cost_per_token' => 0.000002],
                'm2' => ['litellm_provider' => 'openai', 'input_cost_per_token' => 0.000001, 'output_cost_per_token' => 0.000002],
                'm3' => ['litellm_provider' => 'openai', 'input_cost_per_token' => 0.000001, 'output_cost_per_token' => 0.000002],
                'm4' => ['litellm_provider' => 'openai', 'input_cost_per_token' => 0.000001, 'output_cost_per_token' => 0.000002],
            ])
            ->push(['m1' => ['litellm_provider' => 'openai', 'input_cost_per_token' => 0.000009, 'output_cost_per_token' => 0.000009]], 500);
        $sync = app(AiPricingSyncService::class);
        $sync->sync('litellm');
        $failed = $sync->sync('litellm');

        $this->assertSame('failed', $failed['status']);
        $this->assertDatabaseHas('ai_model_pricings', ['source' => 'litellm', 'model' => 'm4', 'input_price_per_million' => 1]);
    }

    public function test_suspiciously_small_payload_is_rejected(): void
    {
        $url = 'https://prices.test/suspicious.json';
        config(['ai-pricing.feeds.litellm.url' => $url]);
        $full = [];
        foreach (['m1', 'm2', 'm3', 'm4'] as $model) {
            $full[$model] = ['litellm_provider' => 'openai', 'input_cost_per_token' => 0.000001, 'output_cost_per_token' => 0.000002];
        }
        Http::fakeSequence($url)->push($full)->push([
            'm1' => ['litellm_provider' => 'openai', 'input_cost_per_token' => 0.000009, 'output_cost_per_token' => 0.000009],
        ]);
        $sync = app(AiPricingSyncService::class);
        $sync->sync('litellm');
        $result = $sync->sync('litellm');

        $this->assertSame('failed', $result['status']);
        $this->assertDatabaseHas('ai_model_pricings', ['source' => 'litellm', 'model' => 'm4', 'input_price_per_million' => 1]);
    }

    public function test_feed_rows_are_ignored_after_freshness_expiry(): void
    {
        $pricing = AiModelPricing::create([
            'provider' => 'openai', 'model' => 'old-model', 'source' => 'litellm',
            'input_price_per_million' => 1, 'output_price_per_million' => 2,
            'priced_at' => now()->subHours(7), 'last_seen_at' => now()->subHours(7),
        ]);

        $this->assertNull(app(\Agentoom\Core\Services\AiPricingResolver::class)->resolve(
            new \Laravel\Ai\Responses\Data\Usage(1_000_000, 0),
            new \Laravel\Ai\Responses\Data\Meta('openai', 'old-model')
        ));
        $this->assertNotNull($pricing->fresh());
    }
}
