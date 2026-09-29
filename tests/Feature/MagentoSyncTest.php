<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Models\SeoProject;
use Agentoom\Core\Services\Api\MagentoService;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class MagentoSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Project $project;

    protected Resource $resource;

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

        $resourceType = ResourceType::create([
            'name' => 'Magento Website',
            'config' => [],
        ]);

        $this->resource = Resource::create([
            'resource_type_id' => $resourceType->id,
            'name' => 'Test Magento Store',
            'data' => [
                'store_url' => 'https://magento.example.com',
                'api_token' => 'test-token',
            ],
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
        ]);

        $this->seoProject = SeoProject::create([
            'project_id' => $this->project->id,
            'domain' => 'magento.example.com',
            'user_id' => $this->user->id,
            'business_model' => 'ecommerce',
            'primary_goal' => 'sales',
            'site_type' => 'existing',
        ]);
    }

    public function test_it_can_sync_magento_orders(): void
    {
        Http::fake([
            'magento.example.com/rest/V1/orders*' => Http::response([
                'items' => [
                    [
                        'entity_id' => 101,
                        'increment_id' => '000000101',
                        'status' => 'processing',
                        'grand_total' => 150.00,
                        'customer_email' => 'customer@example.com',
                        'created_at' => '2026-04-29 10:00:00',
                        'items' => [
                            [
                                'item_id' => 1,
                                'product_id' => 50,
                                'name' => 'Magento Product',
                                'price' => 150.00,
                                'qty_ordered' => 1,
                                'row_total' => 150.00,
                            ],
                        ],
                    ],
                ],
                'total_count' => 1,
            ], 200),
        ]);

        $service = new MagentoService;
        $result = $service->sync(
            entity: 'orders',
            resourceId: (string) $this->resource->id
        );

        $this->assertStringContainsString('Successfully synced 1 orders', $result);
        $this->assertDatabaseHas('seo_orders', [
            'external_id' => '101',
            'order_number' => '000000101',
            'total_amount' => 150.00,
        ]);
        $this->assertDatabaseHas('seo_products', [
            'external_id' => '50',
            'name' => 'Magento Product',
        ]);
        $this->assertDatabaseHas('seo_order_items', [
            'quantity' => 1,
            'price' => 150.00,
        ]);
    }

    public function test_it_can_sync_magento_orders_using_seo_project_id(): void
    {
        $this->seoProject->update([
            'ecommerce_platform' => 'magento',
            'ecommerce_url' => 'https://magento-direct.example.com',
            'ecommerce_api_key' => 'direct-token',
        ]);

        Http::fake([
            'magento-direct.example.com/rest/V1/orders*' => Http::response([
                'items' => [
                    [
                        'entity_id' => 102,
                        'increment_id' => '000000102',
                        'status' => 'processing',
                        'grand_total' => 200.00,
                        'created_at' => '2026-04-29 11:00:00',
                        'items' => [
                            [
                                'item_id' => 2,
                                'product_id' => 51,
                                'name' => 'Direct Magento Product',
                                'price' => 200.00,
                                'qty_ordered' => 1,
                                'row_total' => 200.00,
                            ],
                        ],
                    ],
                ],
                'total_count' => 1,
            ], 200),
        ]);

        $service = new MagentoService;
        $result = $service->sync(
            entity: 'orders',
            seoProjectId: (string) $this->seoProject->id
        );

        $this->assertStringContainsString('Successfully synced 1 orders', $result);
        $this->assertDatabaseHas('seo_orders', [
            'seo_project_id' => $this->seoProject->id,
            'external_id' => '102',
            'order_number' => '000000102',
        ]);
    }

    public function test_it_can_sync_magento_customers(): void
    {
        Http::fake([
            'magento.example.com/rest/V1/customers/search*' => Http::response([
                'items' => [
                    [
                        'id' => 5,
                        'email' => 'john@example.com',
                        'firstname' => 'John',
                        'lastname' => 'Doe',
                        'created_at' => '2026-01-01 12:00:00',
                    ],
                ],
                'total_count' => 1,
            ], 200),
        ]);

        $service = new MagentoService;
        $result = $service->sync(
            entity: 'customers',
            resourceId: (string) $this->resource->id
        );

        $this->assertStringContainsString('Successfully synced 1 customers', $result);
        $this->assertDatabaseHas('seo_customers', [
            'external_id' => '5',
        ]);
    }
}
