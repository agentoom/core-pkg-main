<?php

namespace Agentoom\Core\Tests\Unit;

use Agentoom\Core\Models\AiModelPricing;
use Agentoom\Core\Models\AiUsageLog;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\Provider;
use Agentoom\Core\Services\AiCreditService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;

class UnpricedUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_usage_is_visible_but_does_not_deduct_credits(): void
    {
        Configuration::setValue('app_ai_credits', 10.0);
        app(AiCreditService::class)->logUsage(null, 'unknown_model', new Usage(100, 50), new Meta('custom-driver', 'unlisted-model'));

        $log = AiUsageLog::firstOrFail();
        $this->assertNull($log->cost);
        $this->assertNull($log->provider_cost);
        $this->assertSame('unpriced', $log->pricing_source);
        $this->assertEquals(10.0, (float) Configuration::getValue('app_ai_credits'));
        $this->assertSame(1, AiUsageLog::where('pricing_source', 'unpriced')->count());
    }

    public function test_explicit_free_price_is_billable_at_zero(): void
    {
        Provider::create(['name' => 'openai', 'driver' => 'openai', 'api_key' => 'fake', 'url' => 'https://api.openai.com/v1']);
        AiModelPricing::create(['provider' => 'openai', 'model' => 'free-model', 'source' => 'manual', 'input_price_per_million' => 0, 'output_price_per_million' => 0]);
        Configuration::setValue('app_ai_credits', 10.0);
        app(AiCreditService::class)->logUsage(null, 'free_model', new Usage(100, 50), new Meta('openai', 'free-model'));

        $log = AiUsageLog::firstOrFail();
        $this->assertSame(0.0, $log->cost);
        $this->assertSame(0.0, $log->provider_cost);
        $this->assertSame('manual', $log->pricing_source);
        $this->assertEquals(10.0, (float) Configuration::getValue('app_ai_credits'));
    }
}
