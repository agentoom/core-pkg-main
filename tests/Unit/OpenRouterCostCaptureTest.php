<?php

namespace Agentoom\Core\Tests\Unit;

use Agentoom\Core\Ai\Support\CostCaptureStore;
use Agentoom\Core\Models\AiModelPricing;
use Agentoom\Core\Models\Provider;
use Agentoom\Core\Services\AiCreditService;
use Agentoom\Core\Tests\TestCase;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;

class OpenRouterCostCaptureTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_scoped_provider_cost_without_marking_up_provider_cost(): void
    {
        $capture = app(CostCaptureStore::class);
        $capture->begin();
        $capture->add(0.123);

        $resolution = app(AiCreditService::class)->resolveCost(new Usage(100, 200), new Meta('openrouter', 'some-model'));

        $this->assertNotNull($resolution);
        $this->assertEquals(0.123, $resolution->providerCost);
        $this->assertEquals(0.123 * 1.25, $resolution->creditCost);
        $capture->end();
    }
    public function test_it_captures_json_and_sse_only_inside_a_provider_scope(): void
    {
        $capture = app(CostCaptureStore::class);
        $capture->begin();
        $stream = Utils::streamFor("data: {\"usage\": {\"cost\": 0.007}}\n\ndata: [DONE]\n\n");
        $calls = 0;
        Http::fake(function ($request) use (&$calls, $stream) {
            if ($calls++ === 0) {
                return Http::response(['usage' => ['cost' => 0]], 200, ['Content-Type' => 'application/json']);
            }

            return Http::response($stream, 200, ['Content-Type' => 'text/event-stream']);
        });
        Http::post('https://openrouter.ai/api/v1/chat/completions')->json();
        $this->assertSame(0.0, $capture->total());
        $capture->reset();

        $capture->begin();
        $response = Http::post('https://openrouter.ai/api/v1/chat/completions');
        $body = $response->toPsrResponse()->getBody();
        while (! $body->eof()) {
            $body->read(10);
        }
        $this->assertSame(0.007, $capture->total());
        $capture->reset();

        $capture->begin();
        Http::fake(['example.test/*' => Http::response(['cost' => 49.99], 200, ['Content-Type' => 'application/json'])]);
        Http::post('https://example.test/unrelated')->json();
        $this->assertNull($capture->total());
        $capture->reset();
    }

    public function test_captured_cost_takes_precedence_over_catalog_pricing(): void
    {
        Provider::create(['name' => 'openrouter', 'driver' => 'openrouter', 'api_key' => 'fake-key', 'url' => 'https://openrouter.ai/api/v1']);
        AiModelPricing::create([
            'provider' => 'openrouter', 'model' => 'some-model', 'source' => 'manual',
            'input_price_per_million' => 10, 'output_price_per_million' => 10,
        ]);
        $capture = app(CostCaptureStore::class);
        $capture->begin();
        $capture->add(0.005);

        $resolution = app(AiCreditService::class)->resolveCost(new Usage(1_000_000, 1_000_000), new Meta('openrouter', 'some-model'));

        $this->assertSame('provider_reported', $resolution?->source);
        $this->assertEquals(0.005, $resolution?->providerCost);
        $capture->end();
    }
}
