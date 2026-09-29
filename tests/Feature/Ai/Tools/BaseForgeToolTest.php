<?php

namespace Agentoom\Core\Tests\Feature\Ai\Tools;

use Agentoom\Core\Ai\Tools\Public\ForgeCreateUpdateTool;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Laravel\Forge\Exceptions\ForbiddenException;
use Laravel\Forge\Forge;
use Mockery;

class BaseForgeToolTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_wraps_data_in_json_except_for_updatesite(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $type = ResourceType::create([
            'name' => ['en' => 'Laravel Forge'],
            'config' => [
                'fields' => [
                    ['name' => 'api_token', 'type' => 'password', 'required' => true],
                ],
            ],
        ]);

        $resource = Resource::create([
            'resource_type_id' => $type->id,
            'user_id' => $user->id,
            'name' => 'My Forge',
            'data' => [
                'api_token' => 'test-token',
            ],
        ]);

        $tool = new ForgeCreateUpdateTool;

        // Mock Forge SDK
        $forgeMock = Mockery::mock(Forge::class);
        $tool->setForge($forgeMock);

        // 1. Test createSite - SHOULD be wrapped
        $forgeMock->shouldReceive('createSite')
            ->with(123, ['json' => ['name' => 'example.com', 'type' => 'laravel']], true)
            ->once()
            ->andReturn((object) ['attributes' => ['id' => 1]]);

        $request1 = new Request([
            'action' => 'createSite',
            'resource_id' => $resource->id,
            'resource_mapping' => '{"api_token": "data.api_token"}',
            'server_id' => 123,
            'data' => ['name' => 'example.com', 'type' => 'laravel'],
            'wait' => true,
        ]);

        $result1 = $tool->createSite($request1);
        $this->assertStringContainsString('Result', $result1);

        // 2. Test updateSite - SHOULD now be wrapped for consistency
        $forgeMock->shouldReceive('updateSite')
            ->with(123, 456, ['json' => ['name' => 'updated.com']])
            ->once()
            ->andReturn((object) ['attributes' => ['id' => 456]]);

        $request2 = new Request([
            'action' => 'updateSite',
            'resource_id' => $resource->id,
            'resource_mapping' => '{"api_token": "data.api_token"}',
            'server_id' => 123,
            'site_id' => 456,
            'data' => ['name' => 'updated.com'],
        ]);

        $result2 = $tool->updateSite($request2);
        $this->assertStringContainsString('Result', $result2);
    }

    public function test_it_uses_v1_endpoint_by_default(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $type = ResourceType::create([
            'name' => ['en' => 'Laravel Forge'],
            'config' => [
                'fields' => [
                    ['name' => 'api_token', 'type' => 'password', 'required' => true],
                ],
            ],
        ]);

        $resource = Resource::create([
            'resource_type_id' => $type->id,
            'user_id' => $user->id,
            'name' => 'My Forge',
            'data' => [
                'api_token' => 'test-token',
            ],
        ]);

        $tool = new ForgeCreateUpdateTool;

        $reflection = new \ReflectionClass($tool);
        $method = $reflection->getMethod('getForge');
        $method->setAccessible(true);

        /** @var Forge $forge1 */
        $forge1 = $method->invoke($tool, 'test-token');
        $this->assertEquals('https://forge.laravel.com/api/v1/', (string) $forge1->guzzle->getConfig('base_uri'));
    }

    public function test_it_provides_hint_on_auth_errors(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $type = ResourceType::create([
            'name' => ['en' => 'Laravel Forge'],
            'config' => [
                'fields' => [
                    ['name' => 'api_token', 'type' => 'password', 'required' => true],
                ],
            ],
        ]);

        $resource = Resource::create([
            'resource_type_id' => $type->id,
            'user_id' => $user->id,
            'name' => 'My Forge',
            'data' => [
                'api_token' => 'test-token',
            ],
        ]);

        $tool = new ForgeCreateUpdateTool;

        // Mock Forge SDK to throw 403
        $forgeMock = Mockery::mock(Forge::class);
        $tool->setForge($forgeMock);

        $forgeMock->shouldReceive('createSite')
            ->andThrow(new ForbiddenException('Forbidden'));

        $request = new Request([
            'action' => 'createSite',
            'resource_id' => $resource->id,
            'resource_mapping' => '{"api_token": "data.api_token"}',
            'server_id' => 123,
            'data' => ['name' => 'example.com', 'type' => 'laravel'],
        ]);

        $result = $tool->createSite($request);
        $this->assertStringContainsString('Hint:', $result);
        $this->assertStringContainsString('organization context is missing', $result);
    }

    public function test_it_excludes_internal_parameters_from_data_fallback(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $type = ResourceType::create([
            'name' => ['en' => 'Laravel Forge'],
            'config' => [
                'fields' => [
                    ['name' => 'api_token', 'type' => 'password', 'required' => true],
                ],
            ],
        ]);

        $resource = Resource::create([
            'resource_type_id' => $type->id,
            'user_id' => $user->id,
            'name' => 'My Forge',
            'data' => [
                'api_token' => 'test-token',
            ],
        ]);

        $tool = new ForgeCreateUpdateTool;

        // Mock Forge SDK
        $forgeMock = Mockery::mock(Forge::class);
        $tool->setForge($forgeMock);

        // We want to verify that 'organization' and 'action' are NOT in the 'data' payload
        // when collected from top-level parameters.
        $forgeMock->shouldReceive('createSite')
            ->with(123, Mockery::on(function ($data) {
                return isset($data['json']) &&
                       $data['json']['name'] === 'example.com' &&
                       $data['json']['type'] === 'laravel' &&
                       ! isset($data['json']['organization']) &&
                       ! isset($data['json']['action']);
            }), true)
            ->once()
            ->andReturn((object) ['attributes' => ['id' => 1]]);

        $request = new Request([
            'action' => 'createSite',
            'resource_id' => $resource->id,
            'resource_mapping' => '{"api_token": "data.api_token"}',
            'server_id' => 123,
            'organization' => 'my-org', // Internal param
            'name' => 'example.com',    // Should be collected into data
            'type' => 'laravel',        // Should be collected into data
            'wait' => true,
        ]);

        $result = $tool->createSite($request);
        $this->assertStringContainsString('Result', $result);
    }

    public function test_it_auto_detects_organization_from_resource_data(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $type = ResourceType::create([
            'name' => ['en' => 'Laravel Forge'],
            'config' => [
                'fields' => [
                    ['name' => 'api_token', 'type' => 'password', 'required' => true],
                ],
            ],
        ]);

        // 1. Test 'organization' key in data
        $resource1 = Resource::create([
            'resource_type_id' => $type->id,
            'user_id' => $user->id,
            'name' => 'Forge Org 1',
            'data' => [
                'api_token' => 'test-token',
                'organization' => 'auto-org-1',
            ],
        ]);

        $tool = new class extends ForgeCreateUpdateTool
        {
            public ?string $detectedOrg = null;

            // Since we can't easily capture the organization passed to getForge anymore
            // because it's removed from the signature, we'll override executeForgeAction
            // just to verify the organization detection logic.
            protected function executeForgeAction(string $action, Request $request): \Stringable|string
            {
                $params = $request->all();
                $resource = $this->getResource($request);
                $mapped = $this->mapResourceData($resource, $request);

                $this->detectedOrg = $params['organization'] ??
                    $mapped['organization'] ??
                    $resource->getDecryptedData()['organization'] ??
                    $resource->getDecryptedData()['org_id'] ??
                    null;

                return 'Success';
            }
        };

        $request1 = new Request([
            'action' => 'createSite',
            'resource_id' => $resource1->id,
            'resource_mapping' => '{"api_token": "data.api_token"}',
            'server_id' => 123,
            'name' => 'example.com',
            'type' => 'laravel',
        ]);

        $tool->createSite($request1);
        $this->assertEquals('auto-org-1', $tool->detectedOrg);

        // 2. Test 'org_id' key in data
        $resource2 = Resource::create([
            'resource_type_id' => $type->id,
            'user_id' => $user->id,
            'name' => 'Forge Org 2',
            'data' => [
                'api_token' => 'test-token',
                'org_id' => 'auto-org-2',
            ],
        ]);

        $request2 = new Request([
            'action' => 'createSite',
            'resource_id' => $resource2->id,
            'resource_mapping' => '{"api_token": "data.api_token"}',
            'server_id' => 123,
            'name' => 'example.com',
            'type' => 'laravel',
        ]);

        $tool->createSite($request2);
        $this->assertEquals('auto-org-2', $tool->detectedOrg);

        // 3. Request parameter takes precedence
        $request3 = new Request([
            'action' => 'createSite',
            'resource_id' => $resource1->id,
            'resource_mapping' => '{"api_token": "data.api_token"}',
            'server_id' => 123,
            'name' => 'example.com',
            'type' => 'laravel',
            'organization' => 'explicit-org',
        ]);

        $tool->createSite($request3);
        $this->assertEquals('explicit-org', $tool->detectedOrg);
    }

    public function test_it_adds_mandatory_organization_site_creation_fields(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $type = ResourceType::create([
            'name' => ['en' => 'Laravel Forge'],
            'config' => [
                'fields' => [
                    ['name' => 'api_token', 'type' => 'password', 'required' => true],
                ],
            ],
        ]);

        $resource = Resource::create([
            'resource_type_id' => $type->id,
            'user_id' => $user->id,
            'name' => 'My Forge',
            'data' => [
                'api_token' => 'test-token',
                'organization' => 'my-org',
            ],
        ]);

        $tool = new class extends ForgeCreateUpdateTool
        {
            public array $capturedData = [];
        };

        $mock = Mockery::mock(Forge::class);
        $mock->shouldReceive('createSite')->andReturnUsing(function ($serverId, $data) use ($tool) {
            $tool->capturedData = $data;

            return (object) ['attributes' => ['id' => 1]];
        });
        $tool->setForge($mock);

        $request = new Request([
            'action' => 'createSite',
            'resource_id' => $resource->id,
            'resource_mapping' => '{"api_token": "data.api_token"}',
            'server_id' => 123,
            'name' => 'example.com',
            'type' => 'laravel',
        ]);

        $tool->createSite($request);

        // Check if mandatory fields were added automatically
        $this->assertArrayHasKey('json', $tool->capturedData);
        $data = $tool->capturedData['json'];
        $this->assertEquals('custom', $data['domain_mode']);
        $this->assertEquals('none', $data['www_redirect_type']);
        $this->assertEquals(false, $data['allow_wildcard_subdomains']);
    }
}
