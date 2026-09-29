<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Services\DecodoService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class DecodoServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Configuration::updateOrCreate(['key' => 'decodo_api_key'], ['value' => 'test-api-key']);
    }

    public function test_scrape_payload_and_auth()
    {
        Http::fake();

        $service = new DecodoService;
        $service->scrape('https://example.com', ['target' => 'amazon_product']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://scraper-api.decodo.com/v2/scrape' &&
                   $request->header('Authorization')[0] === 'Basic test-api-key' &&
                   $request->header('Accept')[0] === 'application/json' &&
                   $request['url'] === 'https://example.com' &&
                   $request['proxy_pool'] === 'premium' &&
                   $request['headless'] === 'html' &&
                   $request['parsed'] === true &&
                   $request['target'] === 'amazon_product';
        });
    }

    public function test_search_payload_and_auth()
    {
        Http::fake();

        $service = new DecodoService;
        $service->search('test query');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://scraper-api.decodo.com/v2/scrape' &&
                   $request->header('Authorization')[0] === 'Basic test-api-key' &&
                   $request->header('Accept')[0] === 'application/json' &&
                   $request['query'] === 'test query' &&
                   $request['parsed'] === true;
        });
    }
}
