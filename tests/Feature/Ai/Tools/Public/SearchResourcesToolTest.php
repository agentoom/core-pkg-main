<?php

namespace Agentoom\Core\Tests\Feature\Ai\Tools\Public;

use Agentoom\Core\Ai\Tools\Public\SearchResourcesTool;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

class SearchResourcesToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_types_action(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        ResourceType::create([
            'user_id' => $user->id,
            'name' => 'Credentials',
            'description' => 'Stores API keys and tokens',
            'config' => [
                'fields' => [
                    ['name' => 'api_token', 'type' => 'text', 'required' => true, 'encrypted' => true],
                ],
            ],
        ]);

        $tool = new SearchResourcesTool;
        $output = $tool->handle(new Request(['action' => 'list_types']));

        $this->assertStringContainsString('Credentials', $output);
        $this->assertStringContainsString('api_token', $output);
        $this->assertStringContainsString('(ENCRYPTED)', $output);
    }

    public function test_search_with_type_filter(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $type1 = ResourceType::create(['name' => 'Credentials', 'config' => [['name' => 'key', 'type' => 'text']]]);
        $type2 = ResourceType::create(['name' => 'Other', 'config' => [['name' => 'note', 'type' => 'text']]]);

        Resource::create([
            'resource_type_id' => $type1->id,
            'name' => 'Forge API Key',
            'user_id' => $user->id,
            'data' => ['key' => 'secret-123'],
        ]);

        Resource::create([
            'resource_type_id' => $type2->id,
            'name' => 'Random Note',
            'user_id' => $user->id,
            'data' => ['note' => 'just a note'],
        ]);

        $tool = new SearchResourcesTool;

        // Search for Forge in all types
        $output = $tool->handle(new Request(['action' => 'search', 'query' => 'Forge']));
        $this->assertStringContainsString('Forge API Key', $output);
        $this->assertStringNotContainsString('Random Note', $output);

        // Search in specific type
        $output = $tool->handle(new Request(['action' => 'search', 'query' => '', 'resource_type_id' => $type1->id]));
        $this->assertStringContainsString('Forge API Key', $output);
        $this->assertStringNotContainsString('Random Note', $output);

        // Search in specific type with query
        $output = $tool->handle(new Request(['action' => 'search', 'query' => 'Random', 'resource_type_id' => $type1->id]));
        $this->assertStringContainsString('No relevant resources found', $output);
    }

    public function test_redacted_and_encrypted_fields(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $type = ResourceType::create([
            'name' => 'Security',
            'config' => [
                'fields' => [
                    ['name' => 'password', 'type' => 'text'],
                    ['name' => 'secret_token', 'type' => 'text', 'encrypted' => true],
                    ['name' => 'public_info', 'type' => 'text'],
                ],
            ],
        ]);

        Resource::create([
            'resource_type_id' => $type->id,
            'name' => 'My Account',
            'user_id' => $user->id,
            'data' => [
                'password' => 'pass123',
                'secret_token' => 'token123',
                'public_info' => 'hello',
            ],
        ]);

        $tool = new SearchResourcesTool;
        $output = $tool->handle(new Request(['action' => 'search', 'query' => 'Account']));

        $this->assertStringContainsString('password: [REDACTED]', $output);
        $this->assertStringContainsString('secret_token: [ENCRYPTED_REFERENCE:secret_token]', $output);
        $this->assertStringContainsString('public_info: hello', $output);
    }
}
