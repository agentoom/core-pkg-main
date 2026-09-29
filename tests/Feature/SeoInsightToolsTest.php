<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\SeoContent;
use Agentoom\Core\Models\SeoProduct;
use Agentoom\Core\Models\SeoProductCategory;
use Agentoom\Core\Models\SeoProject;
use Agentoom\Core\Models\User;
use Agentoom\Core\Services\Seo\SeoContentInsightService;
use Agentoom\Core\Services\Seo\SeoInsightService;
use Agentoom\Core\Services\Seo\SeoMetricsSyncService;
use Agentoom\Core\Services\Seo\SeoProductInsightService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SeoInsightToolsTest extends TestCase
{
    use RefreshDatabase;

    protected SeoProject $project;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->project = SeoProject::create([
            'domain' => 'example.com',
            'business_model' => 'ecommerce',
            'primary_goal' => 'sales',
            'site_type' => 'existing',
            'user_id' => $user->id,
        ]);
    }

    public function test_low_ctr_content_insight(): void
    {
        $content = SeoContent::create([
            'seo_project_id' => $this->project->id,
            'title' => 'Low CTR Page',
            'slug' => 'low-ctr',
        ]);

        $metricsSync = app(SeoMetricsSyncService::class);
        $metricsSync->syncContentMetrics($this->project, [
            [
                'slug' => 'low-ctr',
                'date' => now()->subDays(1)->format('Y-m-d'),
                'impressions' => 2000,
                'clicks' => 5,
                'ctr' => 0.0025,
            ],
        ]);

        app(SeoInsightService::class)->refreshContentInsights($this->project);

        $service = app(SeoContentInsightService::class);
        $insights = $service->getLowCTRContent($this->project->id);

        $this->assertCount(1, $insights);
        $this->assertEquals('Low CTR Page', $insights[0]['title']);
        $this->assertEquals('High impressions but low CTR', $insights[0]['reason']);
    }

    public function test_declining_content_insight(): void
    {
        $content = SeoContent::create([
            'seo_project_id' => $this->project->id,
            'title' => 'Declining Page',
            'slug' => 'declining',
        ]);

        $metricsSync = app(SeoMetricsSyncService::class);
        // Current month: 500 impressions, 10 clicks
        $metricsSync->syncContentMetrics($this->project, [
            [
                'slug' => 'declining',
                'date' => now()->subDays(5)->format('Y-m-d'),
                'impressions' => 500,
                'clicks' => 10,
                'ctr' => 2.0,
            ],
        ]);
        // Previous month: 1000 impressions, 50 clicks
        $metricsSync->syncContentMetrics($this->project, [
            [
                'slug' => 'declining',
                'date' => now()->subDays(45)->format('Y-m-d'),
                'impressions' => 1000,
                'clicks' => 50,
                'ctr' => 5.0,
            ],
        ]);

        app(SeoInsightService::class)->refreshContentInsights($this->project);

        $service = app(SeoContentInsightService::class);
        $insights = $service->getDecliningContent($this->project->id);

        $this->assertCount(1, $insights);
        $this->assertEquals('Declining Page', $insights[0]['title']);
        $this->assertEquals(1000, $insights[0]['previous_impressions']);
        $this->assertEquals(500, $insights[0]['current_impressions']);
    }

    public function test_content_gaps_insight(): void
    {
        $product = SeoProduct::create([
            'seo_project_id' => $this->project->id,
            'name' => 'Lonely Product',
            'slug' => 'lonely-product',
        ]);

        $category = SeoProductCategory::create([
            'seo_project_id' => $this->project->id,
            'name' => 'Empty Category',
            'slug' => 'empty-category',
        ]);

        $service = app(SeoProductInsightService::class);
        $insights = $service->getContentGaps($this->project->id);

        // Should return both product and category as they have no linked content
        $this->assertCount(2, $insights);

        // Link content to product
        SeoContent::create([
            'seo_project_id' => $this->project->id,
            'seo_product_id' => $product->id,
            'title' => 'Product Review',
            'slug' => 'product-review',
        ]);

        $insights = $service->getContentGaps($this->project->id);
        $this->assertCount(1, $insights);
        $this->assertEquals('Empty Category', $insights[0]['name']);
    }
}
