<?php

namespace Agentoom\Core\Tests\Feature\Ai\Tools\Public;

use Agentoom\Core\Ai\Tools\Public\ManageResourcesTool;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

class ManageResourcesToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_types_can_handle_keyed_config_fields(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create a resource type with the 'fields' keyed config structure
        $type = ResourceType::create([
            'name' => 'Keyed Type',
            'user_id' => $user->id,
            'description' => 'A type with keyed fields',
            'config' => [
                'fields' => [
                    [
                        'name' => 'api_key',
                        'type' => 'text',
                        'required' => true,
                    ],
                ],
            ],
        ]);

        $tool = new ManageResourcesTool;

        // This should not throw 'Undefined array key "name"'
        $output = $tool->handle(new Request(['action' => 'list_types']));

        $this->assertStringContainsString('Keyed Type', $output);
        $this->assertStringContainsString('api_key', $output);
        $this->assertStringContainsString('required', $output);
    }

    public function test_create_resource_can_handle_keyed_config_fields(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create a resource type with the 'fields' keyed config structure
        $type = ResourceType::create([
            'name' => 'Keyed Type',
            'description' => 'A type with keyed fields',
            'config' => [
                'fields' => [
                    [
                        'name' => 'api_key',
                        'type' => 'text',
                        'required' => true,
                    ],
                ],
            ],
        ]);

        $tool = new ManageResourcesTool;

        $output = $tool->handle(new Request([
            'action' => 'create_resource',
            'resource_type_id' => $type->id,
            'name' => 'Test Resource',
            'data' => [
                'api_key' => 'secret-123',
            ],
        ]));

        $this->assertStringContainsString('Successfully created resource', $output);
        $this->assertStringContainsString('Test Resource', $output);
        $this->assertStringContainsString('api_key: secret-123', $output);
    }
}
