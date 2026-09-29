<?php

namespace Agentoom\Core\Tests\Feature\Ai\Tools\Public;

use Agentoom\Core\Ai\Tools\Public\BrightDataScrapingTool;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

class BrightDataScrapingToolTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Configuration::setValue('brightdata_api_key', 'test-key');
        Configuration::setValue('brightdata_customer_id', 'test-id');
        Configuration::setValue('brightdata_serp_zone', 'test-zone');
        Configuration::setValue('brightdata_scraping_zone', 'test-zone');
        Configuration::setValue('brightdata_scraping_password', 'test-pass');
    }

    public function test_it_can_search_via_tool(): void
    {
        Http::fake([
            '*/serp/get_results*' => Http::response(['results' => [['title' => 'Laravel']]]),
        ]);

        $tool = app(BrightDataScrapingTool::class);
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
            '*' => Http::response('Bright Data content'),
        ]);

        $tool = app(BrightDataScrapingTool::class);
        $request = new Request([
            'action' => 'scrape',
            'url' => 'https://example.com',
        ]);

        $output = (string) $tool->handle($request);

        $this->assertStringContainsString('Bright Data content', $output);
    }
}
