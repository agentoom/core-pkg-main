<?php

namespace Agentoom\Core\Tests\Feature\Ai\Tools\Public;

use Agentoom\Core\Ai\Tools\Public\FirecrawlScrapingTool;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

class FirecrawlScrapingToolTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Configuration::setValue('firecrawl_api_key', 'test-key');
    }

    public function test_it_can_search_via_tool(): void
    {
        Http::fake([
            '*/search' => Http::response(['data' => [['url' => 'https://example.com']]]),
        ]);

        $tool = app(FirecrawlScrapingTool::class);
        $request = new Request([
            'action' => 'search',
            'query' => 'laravel',
        ]);

        $output = (string) $tool->handle($request);

        $this->assertStringContainsString('https://example.com', $output);
    }

    public function test_it_can_scrape_via_tool(): void
    {
        Http::fake([
            '*/scrape' => Http::response(['data' => ['markdown' => '# Hello']]),
        ]);

        $tool = app(FirecrawlScrapingTool::class);
        $request = new Request([
            'action' => 'scrape',
            'url' => 'https://example.com',
        ]);

        $output = (string) $tool->handle($request);

        $this->assertStringContainsString('# Hello', $output);
    }

    public function test_it_can_interact_via_tool(): void
    {
        Http::fake([
            '*/scrape' => Http::response(['data' => ['metadata' => ['scrapeId' => 'test-id']]]),
            '*/scrape/test-id/interact' => Http::response(['success' => true]),
        ]);

        $tool = app(FirecrawlScrapingTool::class);
        $request = new Request([
            'action' => 'interact',
            'url' => 'https://example.com',
            'prompt' => 'click login',
        ]);

        $output = (string) $tool->handle($request);

        $this->assertStringContainsString('test-id', $output);
    }
}
