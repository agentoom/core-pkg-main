<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Tools\Public\GoogleAnalyticsTool;
use Agentoom\Core\Services\Api\GoogleAnalyticsService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Mockery\MockInterface;

class GoogleAnalyticsToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_analytics_tool_dispatches_to_execute()
    {
        $this->mock(GoogleAnalyticsService::class, function (MockInterface $mock) {
            $mock->shouldReceive('execute')
                ->once()
                ->with(
                    'properties/12345:runReport',
                    'POST',
                    ['metrics' => [['name' => 'activeUsers']]],
                    '',
                    []
                )
                ->andReturn('{"rows": []}');
        });

        $tool = new GoogleAnalyticsTool;
        $request = new Request([
            'action' => 'execute',
            'endpoint' => 'properties/12345:runReport',
            'data' => ['metrics' => [['name' => 'activeUsers']]],
        ]);

        $result = $tool->handle($request);
        $this->assertEquals('{"rows": []}', $result);
    }

    public function test_google_analytics_tool_dispatches_to_list_properties()
    {
        $this->mock(GoogleAnalyticsService::class, function (MockInterface $mock) {
            $mock->shouldReceive('listProperties')
                ->once()
                ->with('', [])
                ->andReturn(json_encode(['accountSummaries' => []]));
        });

        $tool = new GoogleAnalyticsTool;
        $request = new Request([
            'action' => 'list_properties',
        ]);

        $result = $tool->handle($request);
        $this->assertStringContainsString('accountSummaries', (string) $result);
    }
}
