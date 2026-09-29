<?php

namespace Agentoom\Core\Tests\Feature\Ai\Tools\Public;

use Agentoom\Core\Ai\Tools\Public\CloudflareCreateUpdateTool;
use Agentoom\Core\Ai\Tools\Public\CloudflareDeleteTool;
use Agentoom\Core\Ai\Tools\Public\CloudflareRetrieveTool;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

class CloudflareToolTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Resource $resource;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $type = ResourceType::create(['name' => ['en' => 'Cloudflare'], 'config' => []]);
        $this->resource = Resource::create([
            'resource_type_id' => $type->id,
            'user_id' => $this->user->id,
            'name' => 'My Cloudflare',
            'description' => 'A test resource',
            'data' => [
                'api_token' => 'test-cloudflare-token',
            ],
        ]);
    }

    public function test_cloudflare_retrieve_tool_performs_get_request(): void
    {
        Http::fake([
            'https://api.cloudflare.com/client/v4/zones' => Http::response(['result' => []], 200),
        ]);

        $tool = app(CloudflareRetrieveTool::class);
        $response = $tool->handle(new Request([
            'resource_id' => (string) $this->resource->id,
            'resource_mapping' => ['api_token' => 'data.api_token'],
            'endpoint' => 'zones',
        ]));

        $this->assertStringContainsString('"result": []', (string) $response);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.cloudflare.com/client/v4/zones' &&
                   $request->method() === 'GET' &&
                   $request->hasHeader('Authorization', 'Bearer test-cloudflare-token');
        });
    }

    public function test_cloudflare_create_update_tool_performs_post_request(): void
    {
        Http::fake([
            'https://api.cloudflare.com/client/v4/zones' => Http::response(['success' => true], 201),
        ]);

        $tool = app(CloudflareCreateUpdateTool::class);
        $response = $tool->handle(new Request([
            'resource_id' => (string) $this->resource->id,
            'resource_mapping' => ['api_token' => 'data.api_token'],
            'method' => 'POST',
            'endpoint' => 'zones',
            'params' => ['name' => 'example.com'],
        ]));

        $this->assertStringContainsString('"success": true', (string) $response);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.cloudflare.com/client/v4/zones' &&
                   $request->method() === 'POST' &&
                   $request['name'] === 'example.com';
        });
    }

    public function test_cloudflare_delete_tool_performs_delete_request(): void
    {
        Http::fake([
            'https://api.cloudflare.com/client/v4/zones/123' => Http::response(['success' => true], 200),
        ]);

        $tool = app(CloudflareDeleteTool::class);
        $response = $tool->handle(new Request([
            'resource_id' => (string) $this->resource->id,
            'resource_mapping' => ['api_token' => 'data.api_token'],
            'endpoint' => 'zones/123',
        ]));

        $this->assertStringContainsString('"success": true', (string) $response);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.cloudflare.com/client/v4/zones/123' &&
                   $request->method() === 'DELETE';
        });
    }

    public function test_retrieve_tool_uses_global_integration_config_when_no_resource_id(): void
    {
        Configuration::setValue('cloudflare_integration', [
            'api_token' => 'global-cloudflare-token',
        ]);

        Http::fake([
            'https://api.cloudflare.com/client/v4/zones' => Http::response(['result' => ['zone1']], 200),
        ]);

        $tool = app(CloudflareRetrieveTool::class);
        $response = $tool->handle(new Request([
            'endpoint' => 'zones',
        ]));

        $this->assertStringContainsString('"result"', (string) $response);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.cloudflare.com/client/v4/zones' &&
                   $request->method() === 'GET' &&
                   $request->hasHeader('Authorization', 'Bearer global-cloudflare-token');
        });
    }

    public function test_retrieve_tool_falls_back_to_env_config_when_no_global_config(): void
    {
        config(['services.cloudflare.api_token' => 'env-cloudflare-token']);

        Http::fake([
            'https://api.cloudflare.com/client/v4/zones' => Http::response(['result' => ['zone1']], 200),
        ]);

        $tool = app(CloudflareRetrieveTool::class);
        $response = $tool->handle(new Request([
            'endpoint' => 'zones',
        ]));

        $this->assertStringContainsString('"result"', (string) $response);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.cloudflare.com/client/v4/zones' &&
                   $request->method() === 'GET' &&
                   $request->hasHeader('Authorization', 'Bearer env-cloudflare-token');
        });
    }

    public function test_retrieve_tool_returns_error_when_no_resources_available(): void
    {
        Http::fake();

        $tool = app(CloudflareRetrieveTool::class);
        $response = $tool->handle(new Request([
            'endpoint' => 'zones',
        ]));

        $this->assertStringContainsString('Cloudflare API Token or Global API Key is missing', (string) $response);
    }
}
