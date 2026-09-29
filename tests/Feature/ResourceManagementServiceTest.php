<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Services\ResourceManagementService;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ResourceManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    private ResourceManagementService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ResourceManagementService::class);
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    // ─── Create Resource Type ───────────────────────────────

    public function test_creates_global_resource_type(): void
    {
        $result = $this->service->createGlobalResourceType(
            name: 'API Server',
            description: 'Stores API connection details',
            config: [
                ['name' => 'api_url', 'type' => 'text', 'required' => true],
                ['name' => 'api_key', 'type' => 'password', 'encrypted' => true],
            ],
        );

        $this->assertStringContainsString('Successfully created resource type', $result);
        $this->assertStringContainsString('API Server', $result);

        $type = ResourceType::whereName('API Server')->first();
        $this->assertNotNull($type);
        $this->assertCount(2, $type->fields);
    }

    public function test_create_global_resource_type_validates_config(): void
    {
        $result = $this->service->createGlobalResourceType(name: 'Bad', description: '', config: []);
        $this->assertStringContainsString('Error', $result);
        $this->assertStringContainsString('config', strtolower($result));

        $result = $this->service->createGlobalResourceType(
            name: 'Bad', description: '', config: [['name' => 'only_name']]
        );
        $this->assertStringContainsString('type', strtolower($result));
    }

    // ─── Update Resource Type ───────────────────────────────

    public function test_updates_global_resource_type(): void
    {
        $type = ResourceType::factory()->create();

        $result = $this->service->updateGlobalResourceType(
            typeId: $type->id,
            name: 'Updated Name',
            description: 'Updated description',
        );

        $this->assertStringContainsString('Successfully updated resource type', $result);

        $type->refresh();
        $this->assertEquals('Updated Name', $type->name_translated);
        $this->assertEquals('Updated description', $type->description);
    }

    public function test_update_global_resource_type_not_found(): void
    {
        $result = $this->service->updateGlobalResourceType(typeId: 99999, name: 'Ghost');
        $this->assertStringContainsString('not found', strtolower($result));
    }

    // ─── Create Resource ────────────────────────────────────

    public function test_creates_global_resource(): void
    {
        $type = ResourceType::factory()->create([
            'config' => [
                'fields' => [
                    ['name' => 'host', 'type' => 'text', 'required' => true],
                    ['name' => 'port', 'type' => 'number'],
                ],
            ],
        ]);

        $result = $this->service->createGlobalResource(
            name: 'My Server',
            description: 'Production server',
            data: ['host' => 'example.com', 'port' => '443'],
            resourceTypeId: $type->id,
        );

        $this->assertStringContainsString('Successfully created resource', $result);
        $this->assertStringContainsString('My Server', $result);

        $resource = Resource::where('name', 'My Server')->first();
        $this->assertNotNull($resource);
        $this->assertEquals('example.com', $resource->data['host']);
        $this->assertEquals('443', $resource->data['port']);
    }

    public function test_create_global_resource_missing_required_fields(): void
    {
        $type = ResourceType::factory()->create([
            'config' => [
                'fields' => [
                    ['name' => 'required_field', 'type' => 'text', 'required' => true],
                ],
            ],
        ]);

        $result = $this->service->createGlobalResource(
            name: 'Incomplete', description: '', data: [], resourceTypeId: $type->id,
        );

        $this->assertStringContainsString('Missing required fields', $result);
        $this->assertStringContainsString('required_field', $result);
    }

    public function test_create_global_resource_type_not_found(): void
    {
        $result = $this->service->createGlobalResource(
            name: 'Ghost', description: '', data: [], resourceTypeId: 99999,
        );

        $this->assertStringContainsString('not found', strtolower($result));
    }

    public function test_create_global_resource_requires_type_id(): void
    {
        $result = $this->service->createGlobalResource(
            name: 'No Type', description: '', data: [],
        );
        $this->assertStringContainsString('resource_type_id', strtolower($result));
    }

    // ─── Update Resource ────────────────────────────────────

    public function test_updates_global_resource(): void
    {
        $type = ResourceType::factory()->create([
            'config' => [
                'fields' => [
                    ['name' => 'url', 'type' => 'text'],
                    ['name' => 'notes', 'type' => 'textarea'],
                ],
            ],
        ]);
        $resource = Resource::factory()->create([
            'resource_type_id' => $type->id,
            'user_id' => $this->user->id,
            'name' => 'Original Name',
            'data' => ['url' => 'https://original.test', 'notes' => 'Initial note'],
        ]);

        $result = $this->service->updateGlobalResource(
            resourceId: $resource->id,
            name: 'Updated Name',
            description: 'Updated description',
            data: ['url' => 'https://updated.test'],
        );

        $this->assertStringContainsString('Successfully updated resource', $result);

        $resource->refresh();
        $this->assertEquals('Updated Name', $resource->name);
        $this->assertEquals('Updated description', $resource->description);
        $this->assertEquals('https://updated.test', $resource->data['url']);
        $this->assertEquals('Initial note', $resource->data['notes']);
    }

    public function test_update_global_resource_not_found(): void
    {
        $result = $this->service->updateGlobalResource(resourceId: 99999, name: 'Ghost');
        $this->assertStringContainsString('not found', strtolower($result));
    }

    // ─── Update with Concatenation ─────────────────────────

    public function test_update_appends_string_fields_global(): void
    {
        $type = ResourceType::factory()->create([
            'config' => [
                'fields' => [
                    ['name' => 'log', 'type' => 'textarea'],
                    ['name' => 'status', 'type' => 'text'],
                ],
            ],
        ]);
        $resource = Resource::factory()->create([
            'resource_type_id' => $type->id,
            'user_id' => $this->user->id,
            'data' => ['log' => 'Initial entry', 'status' => 'active'],
        ]);

        $this->service->updateGlobalResource(
            resourceId: $resource->id,
            data: ['log' => 'Second entry', 'status' => 'inactive'],
            appendFields: ['log'],
        );

        $resource->refresh();
        $this->assertStringContainsString('Initial entry', $resource->data['log']);
        $this->assertStringContainsString('Second entry', $resource->data['log']);
        $this->assertEquals('inactive', $resource->data['status']);
    }

    public function test_update_appends_array_fields_global(): void
    {
        $type = ResourceType::factory()->create([
            'config' => [
                'fields' => [
                    ['name' => 'tags', 'type' => 'text'],
                ],
            ],
        ]);
        $resource = Resource::factory()->create([
            'resource_type_id' => $type->id,
            'user_id' => $this->user->id,
            'data' => ['tags' => ['php', 'laravel']],
        ]);

        $this->service->updateGlobalResource(
            resourceId: $resource->id,
            data: ['tags' => ['database', 'eloquent']],
            appendFields: ['tags'],
        );

        $resource->refresh();
        $this->assertCount(4, $resource->data['tags']);
        $this->assertEquals(['php', 'laravel', 'database', 'eloquent'], $resource->data['tags']);
    }

    public function test_update_multiple_append_fields_global(): void
    {
        $type = ResourceType::factory()->create([
            'config' => [
                'fields' => [
                    ['name' => 'changelog', 'type' => 'textarea'],
                    ['name' => 'notes', 'type' => 'textarea'],
                    ['name' => 'title', 'type' => 'text'],
                ],
            ],
        ]);
        $resource = Resource::factory()->create([
            'resource_type_id' => $type->id,
            'user_id' => $this->user->id,
            'data' => ['changelog' => 'v1.0: Created', 'notes' => 'First note', 'title' => 'Original Title'],
        ]);

        $this->service->updateGlobalResource(
            resourceId: $resource->id,
            data: ['changelog' => 'v2.0: Updated API', 'notes' => 'Second note', 'title' => 'New Title'],
            appendFields: ['changelog', 'notes'],
        );

        $resource->refresh();
        $this->assertStringContainsString('v1.0: Created', $resource->data['changelog']);
        $this->assertStringContainsString('v2.0: Updated API', $resource->data['changelog']);
        $this->assertStringContainsString('First note', $resource->data['notes']);
        $this->assertStringContainsString('Second note', $resource->data['notes']);
        $this->assertEquals('New Title', $resource->data['title']);
    }

    public function test_update_append_first_entry_on_new_field(): void
    {
        $type = ResourceType::factory()->create([
            'config' => [
                'fields' => [
                    ['name' => 'activity_log', 'type' => 'textarea'],
                ],
            ],
        ]);
        $resource = Resource::factory()->create([
            'resource_type_id' => $type->id,
            'user_id' => $this->user->id,
            'data' => [],
        ]);

        $this->service->updateGlobalResource(
            resourceId: $resource->id,
            data: ['activity_log' => 'First activity'],
            appendFields: ['activity_log'],
        );

        $resource->refresh();
        $this->assertEquals('First activity', $resource->data['activity_log']);
    }
}
