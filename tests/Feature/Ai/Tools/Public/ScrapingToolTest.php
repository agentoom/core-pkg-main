<?php

namespace Agentoom\Core\Tests\Feature\Ai\Tools\Public;

use Agentoom\Core\Ai\Tools\Public\ScrapingTool;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

class ScrapingToolTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Configuration::setValue('firecrawl_api_key', 'test-key');
    }

    public function test_scraping_tool_delegates_to_firecrawl(): void
    {
        Http::fake([
            '*/scrape' => Http::response(['data' => ['markdown' => 'Firecrawl output']]),
        ]);

        $tool = app(ScrapingTool::class);

        $request = new Request([
            'action' => 'scrape',
            'provider' => 'firecrawl',
            'url' => 'https://example.com',
        ]);

        $output = (string) $tool->handle($request);

        $this->assertStringContainsString('Firecrawl output', $output);
    }

    public function test_scraping_tool_delegates_to_decodo(): void
    {
        Configuration::setValue('decodo_api_key', 'test-key');
        Http::fake([
            '*/scrape' => Http::response(['content' => 'Decodo output']),
        ]);

        $tool = app(ScrapingTool::class);

        $request = new Request([
            'action' => 'scrape',
            'provider' => 'decodo',
            'url' => 'https://example.com',
        ]);

        $output = (string) $tool->handle($request);

        $this->assertStringContainsString('Decodo output', $output);
    }

    public function test_scraping_tool_delegates_to_brightdata(): void
    {
        Configuration::setValue('brightdata_scraping_zone', 'test-zone');
        Configuration::setValue('brightdata_customer_id', 'test-id');
        Configuration::setValue('brightdata_scraping_password', 'test-pass');
        Http::fake([
            '*' => Http::response('Bright Data output'),
        ]);

        $tool = app(ScrapingTool::class);

        $request = new Request([
            'action' => 'scrape',
            'provider' => 'brightdata',
            'url' => 'https://example.com',
        ]);

        $output = (string) $tool->handle($request);

        $this->assertStringContainsString('Bright Data output', $output);
    }

    public function test_scraping_tool_delegates_to_scrapingbee(): void
    {
        Configuration::setValue('scrapingbee_api_key', 'test-key');
        Http::fake([
            '*' => Http::response('ScrapingBee output'),
        ]);

        $tool = app(ScrapingTool::class);

        $request = new Request([
            'action' => 'scrape',
            'provider' => 'scrapingbee',
            'url' => 'https://example.com',
        ]);

        $output = (string) $tool->handle($request);

        $this->assertStringContainsString('ScrapingBee output', $output);
    }

    public function test_scraping_tool_returns_error_for_unimplemented_provider(): void
    {
        $tool = app(ScrapingTool::class);

        $request = new Request([
            'action' => 'scrape',
            'provider' => 'unknown',
            'url' => 'https://example.com',
        ]);

        $output = (string) $tool->handle($request);

        $this->assertStringContainsString('not implemented', $output);
    }
}
