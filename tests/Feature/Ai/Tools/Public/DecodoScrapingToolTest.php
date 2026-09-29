<?php

namespace Agentoom\Core\Tests\Feature\Ai\Tools\Public;

use Agentoom\Core\Ai\Tools\Public\DecodoScrapingTool;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

class DecodoScrapingToolTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Configuration::setValue('decodo_api_key', 'test-key');
    }

    public function test_it_can_search_via_tool(): void
    {
        Http::fake([
            '*/scrape' => Http::response(['data' => [['title' => 'Laravel']]]),
        ]);

        $tool = app(DecodoScrapingTool::class);
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
            '*/scrape' => Http::response(['content' => 'Decodo content']),
        ]);

        $tool = app(DecodoScrapingTool::class);
        $request = new Request([
            'action' => 'scrape',
            'url' => 'https://example.com',
        ]);

        $output = (string) $tool->handle($request);

        $this->assertStringContainsString('Decodo content', $output);
    }
}
