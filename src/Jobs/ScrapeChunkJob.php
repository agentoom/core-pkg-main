<?php

namespace Agentoom\Core\Jobs;

use Agentoom\Core\Services\BrightDataService;
use Agentoom\Core\Services\DecodoService;
use Agentoom\Core\Services\FirecrawlService;
use Agentoom\Core\Services\ScrapingBeeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ScrapeChunkJob implements ShouldQueue
{
    use Queueable;

    public array $urls;

    public int $scrapedDataTypeId;

    public string $service;

    public ?int $userId;

    public function __construct(
        array $urls,
        int $scrapedDataTypeId,
        string $service = 'decodo',
        ?int $userId = null
    ) {
        $this->urls = $urls;
        $this->scrapedDataTypeId = $scrapedDataTypeId;
        $this->service = $service;
        $this->userId = $userId;
    }

    public function handle(): void
    {
        $provider = match ($this->service) {
            'firecrawl' => app(FirecrawlService::class),
            'brightdata' => app(BrightDataService::class),
            'scrapingbee' => app(ScrapingBeeService::class),
            default => app(DecodoService::class),
        };

        foreach ($this->urls as $url) {
            try {
                $result = $provider->scrape($url);
                if (isset($result['success']) && $result['success']) {
                    $provider->store($url, $result, $this->scrapedDataTypeId, [], $this->userId);
                } else {
                    Log::warning("Scraping failed for URL: {$url}. Error: ".($result['error'] ?? 'Unknown error'));
                }
            } catch (\Exception $e) {
                Log::error("Error scraping URL: {$url}. Message: ".$e->getMessage());
            }
        }
    }
}
