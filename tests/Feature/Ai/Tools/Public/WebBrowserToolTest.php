<?php

namespace Agentoom\Core\Tests\Feature\Ai\Tools\Public;

use Agentoom\Core\Ai\Tools\Public\WebBrowserTool;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

class WebBrowserToolTest extends TestCase
{
    public function test_it_can_fetch_content_via_tool(): void
    {
        Http::fake([
            'https://example.com' => Http::response('Tool output', 200, ['Content-Type' => 'text/plain']),
        ]);

        $tool = new WebBrowserTool;
        $request = new Request([
            'action' => 'fetch',
            'url' => 'https://example.com',
        ]);

        $output = (string) $tool->handle($request);

        $this->assertStringContainsString('Successfully fetched content from: https://example.com', $output);
        $this->assertStringContainsString('Tool output', $output);
    }
}
