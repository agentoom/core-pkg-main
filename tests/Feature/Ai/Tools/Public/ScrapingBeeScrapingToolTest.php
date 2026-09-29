<?php

namespace Agentoom\Core\Tests\Feature\Ai\Tools\Public;

use Agentoom\Core\Ai\Tools\Public\ScrapingBeeScrapingTool;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

class ScrapingBeeScrapingToolTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Configuration::setValue('scrapingbee_api_key', 'test-key');
    }

    public function test_it_can_search_via_tool(): void
    {
        Http::fake([
            '*/google*' => Http::response(['organic_results' => [['title' => 'Laravel']]]),
        ]);

        $tool = app(ScrapingBeeScrapingTool::class);
        $request = new Request([
            'action' => 'search',
            'query' => 'laravel',
        ]);

        $output = (string) $tool->handle($request);

        $this->assertStringContainsString('Laravel', $output);
    }

    public function test_it_can_scrape_via_tool(): void
    {
        Http::fake([
            '*' => Http::response('ScrapingBee content', 200, ['Content-Type' => 'text/html']),
        ]);

        $tool = app(ScrapingBeeScrapingTool::class);
        $request = new Request([
            'action' => 'scrape',
            'url' => 'https://example.com',
        ]);

        $output = (string) $tool->handle($request);

        $this->assertStringContainsString('ScrapingBee content', $output);
    }
}
