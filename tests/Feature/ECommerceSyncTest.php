<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Tools\Public\PrestaShopTool;
use Agentoom\Core\Ai\Tools\Public\WooCommerceTool;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

class ECommerceSyncTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected $resourceType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->resourceType = ResourceType::create(['name' => 'Store', 'config' => []]);
    }

    public function test_woocommerce_sync_orders()
    {
        $resource = Resource::create([
            'resource_type_id' => $this->resourceType->id,
            'name' => 'Woo Store',
            'data' => [
                'url' => 'https://woo.test',
                'consumer_key' => 'ck_123',
                'consumer_secret' => 'cs_123',
            ],
        ]);
        $resource->users()->attach($this->user);

        Http::fake([
            'https://woo.test/wp-json/wc/v3/orders*' => Http::response([
                [
                    'id' => 101,
                    'number' => 'ORD-101',
                    'status' => 'completed',
                    'total' => '100.00',
                    'total_tax' => '10.00',
                    'date_created' => '2026-03-01T10:00:00',
                    'date_modified' => '2026-03-01T11:00:00',
                    'customer_id' => 201,
                    'billing' => [
                        'email' => 'john@test.com',
                        'first_name' => 'John',
                        'last_name' => 'Doe',
                        'city' => 'Milan',
                        'country' => 'Italy',
                        'address_1' => 'Via Roma 1',
                        'address_2' => 'Appartamento 2',
                        'phone' => '123456789',
                    ],
                    'line_items' => [
                        ['id' => 1, 'name' => 'Product A', 'quantity' => 2, 'total' => '80.00', 'total_tax' => '8.00'],
                    ],
                ],
            ], 200, ['X-WP-TotalPages' => '1']),
        ]);

        $tool = new WooCommerceTool;
        $request = new Request([
            'action' => 'sync_orders',
            'resource_id' => (string) $resource->id,
            'resource_mapping' => [
                'store_url' => 'data.url',
                'consumer_key' => 'data.consumer_key',
                'consumer_secret' => 'data.consumer_secret',
            ],
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Successfully synced 1 orders', (string) $result);
        $this->assertDatabaseHas('orders', [
            'external_id' => '101',
            'order_number' => 'ORD-101',
            'total' => 100.00,
            'creation_date' => '2026-03-01 10:00:00',
            'last_updated_date' => '2026-03-01 11:00:00',
        ]);
        $this->assertDatabaseHas('customers', [
            'external_id' => '201',
            'email' => 'john@test.com',
            'name' => 'John Doe',
            'city' => 'Milan',
            'country' => 'Italy',
            'address' => 'Via Roma 1 Appartamento 2',
            'phone' => '123456789',
        ]);
    }

    public function test_prestashop_sync_orders()
    {
        $resource = Resource::create([
            'resource_type_id' => $this->resourceType->id,
            'name' => 'PS Store',
            'data' => [
                'url' => 'https://ps.test',
                'api_key' => 'api_123',
            ],
        ]);
        $resource->users()->attach($this->user);

        Http::fake([
            'https://ps.test/api/orders?display=full&limit=0%2C250&output_format=JSON&sort=id_ASC' => Http::response([
                'orders' => [
                    [
                        'id' => 501,
                        'reference' => 'REF-501',
                        'current_state' => '4',
                        'id_customer' => '601',
                        'total_paid' => '150.00',
                        'total_paid_tax_excl' => '130.00',
                        'total_paid_tax_incl' => '150.00',
                        'date_add' => '2026-03-05 12:00:00',
                        'date_upd' => '2026-03-05 13:00:00',
                        'associations' => [
                            'order_rows' => [
                                [
                                    'id' => 10,
                                    'product_name' => 'Product B',
                                    'product_quantity' => 1,
                                    'unit_price_tax_excl' => '130.00',
                                    'unit_price_tax_incl' => '150.00',
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $tool = new PrestaShopTool;
        $request = new Request([
            'action' => 'sync_orders',
            'resource_id' => (string) $resource->id,
            'resource_mapping' => [
                'store_url' => 'data.url',
                'api_key' => 'data.api_key',
            ],
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Successfully synced 1 orders', (string) $result);
        $this->assertDatabaseHas('orders', [
            'external_id' => '501',
            'order_number' => 'REF-501',
            'total' => 150.00,
            'creation_date' => '2026-03-05 12:00:00',
            'last_updated_date' => '2026-03-05 13:00:00',
        ]);
        $this->assertDatabaseHas('customers', [
            'external_id' => '601',
        ]);
    }

    public function test_woocommerce_sync_customers()
    {
        $resource = Resource::create([
            'resource_type_id' => $this->resourceType->id,
            'name' => 'Woo Store',
            'data' => [
                'url' => 'https://woo.test',
                'consumer_key' => 'ck_123',
                'consumer_secret' => 'cs_123',
            ],
        ]);
        $resource->users()->attach($this->user);

        Http::fake([
            'https://woo.test/wp-json/wc/v3/customers*' => Http::response([
                [
                    'id' => 201,
                    'email' => 'john@test.com',
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'total_spent' => '500.00',
                    'billing' => [
                        'city' => 'Milan',
                        'country' => 'Italy',
                        'address_1' => 'Via Roma 1',
                        'address_2' => '',
                        'phone' => '123456789',
                    ],
                ],
            ], 200, ['X-WP-TotalPages' => '1']),
        ]);

        $tool = new WooCommerceTool;
        $request = new Request([
            'action' => 'sync_customers',
            'resource_id' => (string) $resource->id,
            'resource_mapping' => [
                'store_url' => 'data.url',
                'consumer_key' => 'data.consumer_key',
                'consumer_secret' => 'data.consumer_secret',
            ],
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Successfully synced 1 customers', (string) $result);
        $this->assertDatabaseHas('customers', [
            'external_id' => '201',
            'email' => 'john@test.com',
            'first_name' => 'John',
            'total_bought' => 500.00,
            'city' => 'Milan',
            'country' => 'Italy',
            'phone' => '123456789',
        ]);
    }

    public function test_prestashop_sync_customers()
    {
        $resource = Resource::create([
            'resource_type_id' => $this->resourceType->id,
            'name' => 'PS Store',
            'data' => [
                'url' => 'https://ps.test',
                'api_key' => 'api_123',
            ],
        ]);
        $resource->users()->attach($this->user);

        Http::fake([
            'https://ps.test/api/customers*' => Http::response([
                'customers' => [
                    [
                        'id' => 601,
                        'email' => 'jane@test.com',
                        'firstname' => 'Jane',
                        'lastname' => 'Smith',
                    ],
                ],
            ], 200),
        ]);

        $tool = new PrestaShopTool;
        $request = new Request([
            'action' => 'sync_customers',
            'resource_id' => (string) $resource->id,
            'resource_mapping' => [
                'store_url' => 'data.url',
                'api_key' => 'data.api_key',
            ],
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Successfully synced 1 customers', (string) $result);
        $this->assertDatabaseHas('customers', [
            'external_id' => '601',
            'email' => 'jane@test.com',
            'first_name' => 'Jane',
        ]);
    }
}
