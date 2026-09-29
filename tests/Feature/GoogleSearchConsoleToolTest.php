<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Tools\Public\GoogleSearchConsoleTool;
use Agentoom\Core\Services\Api\GoogleSearchConsoleService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Mockery\MockInterface;

class GoogleSearchConsoleToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_search_console_tool_dispatches_to_execute()
    {
        $this->mock(GoogleSearchConsoleService::class, function (MockInterface $mock) {
            $mock->shouldReceive('execute')
                ->once()
                ->with(
                    'https://example.com/',
                    'searchAnalytics/query',
                    'POST',
                    ['startDate' => '2023-10-01'],
                    '',
                    []
                )
                ->andReturn('{"rows": []}');
        });

        $tool = new GoogleSearchConsoleTool;
        $request = new Request([
            'action' => 'execute',
            'website_url' => 'https://example.com/',
            'endpoint' => 'searchAnalytics/query',
            'method' => 'POST',
            'data' => ['startDate' => '2023-10-01'],
        ]);

        $result = $tool->handle($request);
        $this->assertEquals('{"rows": []}', $result);
    }

    public function test_google_search_console_tool_dispatches_to_inspect_url()
    {
        $this->mock(GoogleSearchConsoleService::class, function (MockInterface $mock) {
            $mock->shouldReceive('inspectUrl')
                ->once()
                ->with(
                    'https://example.com/page1',
                    'https://example.com/',
                    '',
                    []
                )
                ->andReturn('{"verdict": "PASS"}');
        });

        $tool = new GoogleSearchConsoleTool;
        $request = new Request([
            'action' => 'inspect_url',
            'inspection_url' => 'https://example.com/page1',
            'website_url' => 'https://example.com/',
        ]);

        $result = $tool->handle($request);
        $this->assertEquals('{"verdict": "PASS"}', $result);
    }

    public function test_google_search_console_tool_dispatches_to_list_sites()
    {
        $this->mock(GoogleSearchConsoleService::class, function (MockInterface $mock) {
            $mock->shouldReceive('listSites')
                ->once()
                ->with('', [])
                ->andReturn(json_encode(['siteEntry' => []]));
        });

        $tool = new GoogleSearchConsoleTool;
        $request = new Request([
            'action' => 'list_sites',
        ]);

        $result = $tool->handle($request);
        $this->assertStringContainsString('siteEntry', (string) $result);
    }
}
