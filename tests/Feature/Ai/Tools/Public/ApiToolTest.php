<?php

namespace Agentoom\Core\Tests\Feature\Ai\Tools\Public;

use Agentoom\Core\Ai\Tools\Public\ApiTool;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

class ApiToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_tool_performs_get_request(): void
    {
        Http::fake([
            'https://api.example.com/users' => Http::response(['id' => 1, 'name' => 'John Doe'], 200),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $tool = new ApiTool;
        $response = $tool->handle(new Request([
            'method' => 'GET',
            'url' => 'https://api.example.com/users',
        ]));

        $data = json_decode($response, true);
        $this->assertEquals(200, $data['status']);
        $this->assertEquals(['id' => 1, 'name' => 'John Doe'], $data['body']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.example.com/users' &&
                   $request->method() === 'GET';
        });
    }

    public function test_api_tool_performs_post_request_with_body(): void
    {
        Http::fake([
            'https://api.example.com/users' => Http::response(['id' => 2], 201),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $tool = new ApiTool;
        $response = $tool->handle(new Request([
            'method' => 'POST',
            'url' => 'https://api.example.com/users',
            'params' => ['name' => 'Jane Doe'],
        ]));

        $data = json_decode($response, true);
        $this->assertEquals(201, $data['status']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.example.com/users' &&
                   $request->method() === 'POST' &&
                   $request['name'] === 'Jane Doe';
        });
    }

    public function test_api_tool_with_bearer_auth(): void
    {
        Http::fake([
            'https://api.example.com/protected' => Http::response(['status' => 'ok']),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $tool = new ApiTool;
        $tool->handle(new Request([
            'method' => 'GET',
            'url' => 'https://api.example.com/protected',
            'auth_type' => 'bearer',
            'auth_resources' => ['token' => 'secret-token'],
        ]));

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer secret-token');
        });
    }

    public function test_api_tool_with_basic_auth(): void
    {
        Http::fake([
            'https://api.example.com/basic' => Http::response(['status' => 'ok']),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $tool = new ApiTool;
        $tool->handle(new Request([
            'method' => 'GET',
            'url' => 'https://api.example.com/basic',
            'auth_type' => 'basic',
            'auth_resources' => [
                'username' => 'user',
                'password' => 'pass',
            ],
        ]));

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Basic '.base64_encode('user:pass'));
        });
    }

    public function test_api_tool_with_api_key_header(): void
    {
        Http::fake([
            'https://api.example.com/key' => Http::response(['status' => 'ok']),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $tool = new ApiTool;
        $tool->handle(new Request([
            'method' => 'GET',
            'url' => 'https://api.example.com/key',
            'auth_type' => 'api_key_header',
            'auth_resources' => [
                'key' => 'my-api-key',
                'name' => 'X-Custom-Key',
            ],
        ]));

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-Custom-Key', 'my-api-key');
        });
    }

    public function test_api_tool_with_api_key_query(): void
    {
        Http::fake([
            'https://api.example.com/key*' => Http::response(['status' => 'ok']),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $tool = new ApiTool;
        $tool->handle(new Request([
            'method' => 'GET',
            'url' => 'https://api.example.com/key',
            'auth_type' => 'api_key_query',
            'auth_resources' => [
                'key' => 'my-api-key',
                'name' => 'api_key',
            ],
        ]));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api_key=my-api-key');
        });
    }

    public function test_api_tool_with_resource_mapping(): void
    {
        Http::fake([
            'https://api.example.com/resource' => Http::response(['status' => 'ok']),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $type = ResourceType::create(['name' => ['en' => 'API Creds'], 'config' => []]);
        $resource = Resource::create([
            'resource_type_id' => $type->id,
            'user_id' => $user->id,
            'name' => 'My API',
            'data' => [
                'api_token' => 'resource-token',
            ],
        ]);

        $tool = new ApiTool;
        $tool->handle(new Request([
            'method' => 'GET',
            'url' => 'https://api.example.com/resource',
            'auth_type' => 'bearer',
            'resource_id' => $resource->id,
            'resource_mapping' => [
                'token' => 'data.api_token',
            ],
        ]));

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer resource-token');
        });
    }
}
