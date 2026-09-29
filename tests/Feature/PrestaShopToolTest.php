<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Tools\Public\PrestaShopTool;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Models\SeoProject;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

class PrestaShopToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_prestashop_tool_sync_orders()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $project = Project::create([
            'name' => 'Test Project',
            'user_id' => $user->id,
        ]);

        $resourceType = ResourceType::create([
            'name' => 'PrestaShop Website',
            'config' => [],
        ]);

        $resource = Resource::create([
            'resource_type_id' => $resourceType->id,
            'name' => 'My Test PrestaShop Store',
            'description' => 'A test PrestaShop store',
            'data' => [
                'url' => 'https://test-prestashop.com',
                'api_key' => 'PK_TEST',
            ],
            'project_id' => $project->id,
            'user_id' => $user->id,
        ]);

        $seoProject = SeoProject::create([
            'project_id' => $project->id,
            'domain' => 'test-prestashop.com',
            'user_id' => $user->id,
            'business_model' => 'ecommerce',
            'primary_goal' => 'sales',
            'site_type' => 'existing',
        ]);

        Http::fake([
            'https://test-prestashop.com/api/orders*' => function ($request) {
                // Verify sort parameter in URL
                if (! str_contains($request->url(), 'sort=id_ASC')) {
                    return Http::response(['error' => 'Invalid sort parameter'], 400);
                }

                return Http::response([
                    'orders' => [
                        ['id' => 1, 'reference' => 'REF1', 'total_paid' => 100],
                    ],
                ], 200);
            },
        ]);

        $tool = new PrestaShopTool;
        $request = new Request([
            'action' => 'sync',
            'entity' => 'orders',
            'resource_id' => (string) $resource->id,
            'resource_mapping' => [
                'store_url' => 'data.url',
                'api_key' => 'data.api_key',
            ],
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Successfully synced 1 orders', (string) $result);
        $this->assertDatabaseHas('seo_orders', ['external_id' => '1']);
    }

    public function test_prestashop_tool_sync_customers()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $project = Project::create([
            'name' => 'Test Project',
            'user_id' => $user->id,
        ]);

        $resourceType = ResourceType::create([
            'name' => 'PrestaShop Website',
            'config' => [],
        ]);

        $resource = Resource::create([
            'resource_type_id' => $resourceType->id,
            'name' => 'My Test PrestaShop Store',
            'data' => [
                'url' => 'https://test-prestashop.com',
                'api_key' => 'PK_TEST',
            ],
            'project_id' => $project->id,
            'user_id' => $user->id,
        ]);

        $seoProject = SeoProject::create([
            'project_id' => $project->id,
            'domain' => 'test-prestashop.com',
            'user_id' => $user->id,
            'business_model' => 'ecommerce',
            'primary_goal' => 'sales',
            'site_type' => 'existing',
        ]);

        Http::fake([
            'https://test-prestashop.com/api/customers*' => Http::response([
                'customers' => [
                    ['id' => 1, 'email' => 'test@example.com', 'firstname' => 'John', 'lastname' => 'Doe'],
                ],
            ], 200),
        ]);

        $tool = new PrestaShopTool;
        $request = new Request([
            'action' => 'sync',
            'entity' => 'customers',
            'resource_id' => (string) $resource->id,
            'resource_mapping' => [
                'store_url' => 'data.url',
                'api_key' => 'data.api_key',
            ],
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Successfully synced 1 customers', (string) $result);
        $this->assertDatabaseHas('seo_customers', ['external_id' => '1']);
    }

    public function test_prestashop_tool_fails_with_invalid_resource_id()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $tool = new PrestaShopTool;
        $request = new Request([
            'action' => 'sync',
            'resource_id' => '999',
            'resource_mapping' => [
                'store_url' => 'data.url',
                'api_key' => 'data.api_key',
            ],
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('Error: Could not resolve the specified SEO Project', (string) $result);
    }
}
