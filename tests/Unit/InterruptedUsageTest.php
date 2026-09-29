<?php

namespace Agentoom\Core\Tests\Unit;

use Agentoom\Core\Ai\Support\CostCaptureStore;
use Agentoom\Core\Models\AiUsageLog;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Services\AiCreditService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InterruptedUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_abandoned_stream_bills_the_reported_cost(): void
    {
        Configuration::setValue('app_ai_credits', 10.0);
        $capture = app(CostCaptureStore::class);
        $capture->begin();
        $capture->add(0.5);

        $billed = app(AiCreditService::class)->flushCapturedUsage(null, 'agent_interaction_interrupted', 'openrouter', 'some-model');

        $this->assertTrue($billed);

        $log = AiUsageLog::firstOrFail();
        $this->assertSame('provider_reported', $log->pricing_source);
        $this->assertEquals(0.5, $log->provider_cost);
        $this->assertEquals(0.625, $log->cost);
        $this->assertEquals(9.375, (float) Configuration::getValue('app_ai_credits'));
        $this->assertFalse($capture->isActive());
    }

    public function test_abandoned_stream_without_a_reported_cost_bills_nothing(): void
    {
        Configuration::setValue('app_ai_credits', 10.0);
        $capture = app(CostCaptureStore::class);
        $capture->begin();

        $this->assertFalse(app(AiCreditService::class)->flushCapturedUsage(null, 'agent_interaction_interrupted', 'openrouter', 'some-model'));

        $this->assertSame(0, AiUsageLog::count());
        $this->assertEquals(10.0, (float) Configuration::getValue('app_ai_credits'));
        $this->assertFalse($capture->isActive());
    }

    public function test_stream_that_already_completed_is_not_billed_twice(): void
    {
        Configuration::setValue('app_ai_credits', 10.0);
        $capture = app(CostCaptureStore::class);
        $capture->begin();
        $capture->add(0.5);
        $capture->end();

        $this->assertFalse(app(AiCreditService::class)->flushCapturedUsage(null, 'agent_interaction_interrupted', 'openrouter', 'some-model'));

        $this->assertSame(0, AiUsageLog::count());
        $this->assertEquals(10.0, (float) Configuration::getValue('app_ai_credits'));
    }
}
