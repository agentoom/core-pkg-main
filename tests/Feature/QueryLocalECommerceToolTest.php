<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Tools\Public\QueryLocalECommerceTool;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Models\SeoOrder;
use Agentoom\Core\Models\SeoProject;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

class QueryLocalECommerceToolTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected ResourceType $resourceType;

    protected Resource $resource;

    protected Project $project;

    protected SeoProject $seoProject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->be($this->user);

        $this->project = Project::create([
            'name' => 'Test Project',
            'user_id' => $this->user->id,
        ]);

        $this->resourceType = ResourceType::create([
            'name' => 'WooCommerce Website',
            'config' => [],
        ]);

        $this->resource = Resource::create([
            'resource_type_id' => $this->resourceType->id,
            'name' => 'Test Store',
            'description' => 'A test WooCommerce store',
            'data' => ['store_url' => 'https://example.com'],
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
        ]);

        $this->seoProject = SeoProject::create([
            'project_id' => $this->project->id,
            'domain' => 'example.com',
            'user_id' => $this->user->id,
            'business_model' => 'ecommerce',
            'primary_goal' => 'sales',
            'site_type' => 'new',
            'description' => 'A test ecommerce store',
        ]);
    }

    public function test_it_can_store_orders(): void
    {
        $tool = new QueryLocalECommerceTool;
        $request = new Request([
            'action' => 'store',
            'resource_id' => (string) $this->resource->id,
            'orders' => [
                [
                    'external_id' => '1001',
                    'order_number' => '#1001',
                    'status' => 'completed',
                    'payment_method' => 'bacs',
                    'total' => 150.50,
                    'ordered_at' => '2026-04-01T12:00:00Z',
                    'lines' => [
                        [
                            'product_name' => 'Product A',
                            'quantity' => 2,
                            'price' => 50.00,
                            'total' => 100.00,
                        ],
                        [
                            'product_name' => 'Product B',
                            'quantity' => 1,
                            'price' => 50.50,
                            'total' => 50.50,
                        ],
                    ],
                ],
            ],
        ]);

        $response = $tool->handle($request);

        $this->assertStringContainsString('Successfully stored/updated 1 orders', (string) $response);
        $this->assertDatabaseHas('seo_orders', [
            'external_id' => '1001',
            'total_amount' => 150.50,
        ]);
        $this->assertDatabaseCount('seo_order_items', 2);
    }

    public function test_it_can_query_orders(): void
    {
        // Setup existing order
        $order = SeoOrder::create([
            'seo_project_id' => $this->seoProject->id,
            'external_id' => '2001',
            'total_amount' => 200.00,
            'status' => 'pending',
        ]);

        $tool = new QueryLocalECommerceTool;
        $request = new Request([
            'action' => 'query',
            'sql' => "SELECT status, total_amount FROM seo_orders WHERE external_id = '2001'",
        ]);

        $response = $tool->handle($request);

        $this->assertStringContainsString('"status": "pending"', (string) $response);
        $this->assertStringContainsString('"total_amount": "200.00"', (string) $response);
    }

    public function test_it_rejects_non_select_queries(): void
    {
        $tool = new QueryLocalECommerceTool;
        $request = new Request([
            'action' => 'query',
            'sql' => 'DELETE FROM seo_orders',
        ]);

        $response = $tool->handle($request);

        $this->assertStringContainsString('Only SELECT queries are allowed', (string) $response);
    }

    public function test_it_blocks_forbidden_keywords(): void
    {
        $tool = new QueryLocalECommerceTool;
        $request = new Request([
            'action' => 'query',
            'sql' => 'SELECT * FROM seo_orders; DROP TABLE seo_orders',
        ]);

        $response = $tool->handle($request);

        $this->assertStringContainsString('Forbidden SQL keyword detected: DROP', (string) $response);
    }
}
