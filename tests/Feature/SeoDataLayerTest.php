<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\SeoProduct;
use Agentoom\Core\Models\SeoProject;
use Agentoom\Core\Models\User;
use Agentoom\Core\Services\Seo\SeoContentSyncService;
use Agentoom\Core\Services\Seo\SeoDataLayerQueryService;
use Agentoom\Core\Services\Seo\SeoInsightService;
use Agentoom\Core\Services\Seo\SeoMetricsSyncService;
use Agentoom\Core\Services\Seo\SeoOrderSyncService;
use Agentoom\Core\Services\Seo\SeoProductSyncService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SeoDataLayerTest extends TestCase
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

    public function test_product_sync_preserves_meta_if_missing_in_source(): void
    {
        $product = SeoProduct::create([
            'seo_project_id' => $this->project->id,
            'external_id' => 'p1',
            'name' => 'Original Name',
            'slug' => 'original-name',
            'meta_title' => 'Agent Generated Title',
        ]);

        $syncService = app(SeoProductSyncService::class);
        $syncService->sync($this->project, [
            [
                'external_id' => 'p1',
                'name' => 'Updated Name',
                // meta_title missing here
            ],
        ]);

        $product->refresh();
        $this->assertEquals('Updated Name', $product->name);
        $this->assertEquals('Agent Generated Title', $product->meta_title);
    }

    public function test_full_data_layer_and_query_service(): void
    {
        // 1. Sync Products
        $productSync = app(SeoProductSyncService::class);
        $productSync->sync($this->project, [
            [
                'external_id' => 'prod_1',
                'name' => 'Cool Gadget',
                'price' => 100,
                'category' => ['name' => 'Electronics', 'slug' => 'electronics'],
            ],
        ]);

        $product = SeoProduct::where('external_id', 'prod_1')->first();

        // 2. Sync Orders
        $orderSync = app(SeoOrderSyncService::class);
        $orderSync->sync($this->project, [
            [
                'external_id' => 'ord_1',
                'total_amount' => 200,
                'items' => [
                    ['product_external_id' => 'prod_1', 'quantity' => 2, 'price' => 100],
                ],
                'created_at' => now()->subDays(5),
            ],
        ]);

        // 3. Sync Metrics
        $metricsSync = app(SeoMetricsSyncService::class);
        $metricsSync->syncProductMetrics($this->project, [
            [
                'product_external_id' => 'prod_1',
                'date' => now()->subDays(1)->format('Y-m-d'),
                'impressions' => 1000,
                'clicks' => 10,
                'ctr' => 1.0,
            ],
        ]);

        // 4. Refresh Insights
        $insightService = app(SeoInsightService::class);
        $insightService->refreshProductInsights($this->project);

        // 5. Query via Service
        $queryService = app(SeoDataLayerQueryService::class);
        $topProducts = $queryService->getTopProducts($this->project->id);

        $this->assertCount(1, $topProducts);
        $this->assertEquals('Cool Gadget', $topProducts->first()->name);
        $this->assertEquals(200, $topProducts->first()->insight->total_revenue);
        $this->assertEquals(1.0, $topProducts->first()->insight->last_30d_ctr);

        // 6. Test Opportunities
        $opportunities = $queryService->getProductOpportunities($this->project->id);
        $this->assertCount(1, $opportunities);
        $this->assertEquals('Cool Gadget', $opportunities->first()->name);
    }

    public function test_content_opportunities(): void
    {
        $contentSync = app(SeoContentSyncService::class);
        $contentSync->sync($this->project, [
            [
                'title' => 'Missing Meta Page',
                'slug' => 'missing-meta',
                'type' => 'page',
            ],
        ]);

        $metricsSync = app(SeoMetricsSyncService::class);
        $metricsSync->syncContentMetrics($this->project, [
            [
                'slug' => 'missing-meta',
                'date' => now()->subDays(1)->format('Y-m-d'),
                'impressions' => 1000,
                'clicks' => 5,
                'ctr' => 0.5,
            ],
        ]);

        $insightService = app(SeoInsightService::class);
        $insightService->refreshContentInsights($this->project);

        $queryService = app(SeoDataLayerQueryService::class);
        $opps = $queryService->getContentOpportunities($this->project->id);

        $this->assertCount(1, $opps);
        $this->assertEquals('Missing Meta Page', $opps->first()->title);

        $noMeta = $queryService->getContentWithoutMeta($this->project->id);
        $this->assertCount(1, $noMeta);
    }
}
