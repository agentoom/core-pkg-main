<?php

namespace Agentoom\Core\Tests\Feature\Ai\Tools\Public;

use Agentoom\Core\Ai\Tools\BaseResourceTool;
use Agentoom\Core\Ai\Tools\Public\ManageResourcesTool;
use Agentoom\Core\Execution\ActionExecution;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Tools\Request;

class EncryptedResourceToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_encrypted_field_shows_reference_to_ai(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create a resource type with an encrypted field
        $type = ResourceType::create([
            'name' => 'Secure Type',
            'description' => 'A type with encrypted fields',
            'config' => [
                'fields' => [
                    [
                        'name' => 'api_key',
                        'type' => 'text',
                        'encrypted' => true,
                    ],
                    [
                        'name' => 'public_info',
                        'type' => 'text',
                        'encrypted' => false,
                    ],
                ],
            ],
        ]);

        $resource = Resource::create([
            'resource_type_id' => $type->id,
            'name' => 'My Secure Resource',
            'data' => [
                'api_key' => 'very-secret-value',
                'public_info' => 'visible-value',
            ],
        ]);

        $tool = new ManageResourcesTool;

        $output = (string) $tool->handle(new Request([
            'action' => 'get_resource',
            'resource_id' => $resource->id,
        ]));

        // Should NOT contain the secret value
        $this->assertStringNotContainsString('very-secret-value', $output);
        // Should contain the reference
        $this->assertStringContainsString('[ENCRYPTED_REFERENCE:api_key]', $output);
        // Should contain the public info
        $this->assertStringContainsString('public_info: visible-value', $output);
    }

    public function test_base_resource_tool_can_resolve_encrypted_field_via_mapping(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $type = ResourceType::create([
            'name' => 'Secure Type',
            'config' => [
                'fields' => [
                    ['name' => 'api_key', 'type' => 'text', 'encrypted' => true],
                ],
            ],
        ]);

        $resource = Resource::create([
            'resource_type_id' => $type->id,
            'name' => 'My Secure Resource',
            'user_id' => $user->id,
            'data' => [
                'api_key' => 'very-secret-value',
            ],
        ]);

        // Create a dummy tool extending BaseResourceTool
        $tool = new class extends BaseResourceTool
        {
            public function description(): string
            {
                return 'test tool';
            }

            public function schema(JsonSchema $schema): array
            {
                return [];
            }

            protected function requiredMappingFields(): array
            {
                return ['key' => 'API Key'];
            }

            public function executeTestAction(Request $request)
            {
                // Simulate ActionExecution binding like in real tools to ensure redaction is active
                $agent = \Mockery::mock(Agent::class);
                app()->instance(ActionExecution::class, new ActionExecution($agent, $this, $request));

                $resource = $this->getResource($request);
                $mapped = $this->mapResourceData($resource, $request);

                return $mapped['key'];
            }
        };

        $output = $tool->executeTestAction(new Request([
            'resource_id' => $resource->id,
            'resource_mapping' => [
                'key' => 'data.api_key',
            ],
        ]));

        // Tool should get the decrypted value
        $this->assertEquals('very-secret-value', $output);
    }

    public function test_base_resource_tool_can_resolve_literal_encrypted_reference_in_mapping(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $type = ResourceType::create([
            'name' => 'Secure Type',
            'config' => [['name' => 'api_key', 'type' => 'text', 'encrypted' => true]],
        ]);

        $resource = Resource::create([
            'resource_type_id' => $type->id,
            'name' => 'My Secure Resource',
            'user_id' => $user->id,
            'data' => ['api_key' => 'very-secret-value'],
        ]);

        $tool = new class extends BaseResourceTool
        {
            public function description(): string
            {
                return 'test tool';
            }

            public function schema(JsonSchema $schema): array
            {
                return [];
            }

            protected function requiredMappingFields(): array
            {
                return ['key' => 'API Key'];
            }

            public function executeTestAction(Request $request)
            {
                // Simulate ActionExecution binding like in real tools to ensure redaction is active
                $agent = \Mockery::mock(Agent::class);
                app()->instance(ActionExecution::class, new ActionExecution($agent, $this, $request));

                $resource = $this->getResource($request);
                $mapped = $this->mapResourceData($resource, $request);

                return $mapped['key'];
            }
        };

        // Agent passes literal reference in mapping
        $output = $tool->executeTestAction(new Request([
            'resource_id' => $resource->id,
            'resource_mapping' => [
                'key' => '[ENCRYPTED_REFERENCE:api_key]',
            ],
        ]));

        $this->assertEquals('very-secret-value', $output);
    }

    public function test_base_resource_tool_can_resolve_literal_encrypted_reference_in_params(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $type = ResourceType::create([
            'name' => 'Secure Type',
            'config' => [['name' => 'api_key', 'type' => 'text', 'encrypted' => true]],
        ]);

        $resource = Resource::create([
            'resource_type_id' => $type->id,
            'name' => 'My Secure Resource',
            'user_id' => $user->id,
            'data' => ['api_key' => 'very-secret-value'],
        ]);

        $tool = new class extends BaseResourceTool
        {
            public function description(): string
            {
                return 'test tool';
            }

            public function schema(JsonSchema $schema): array
            {
                return [];
            }

            protected function requiredMappingFields(): array
            {
                return [];
            }

            public function executeTestAction(Request $request)
            {
                $params = $this->getCleanParams($request, 'params');

                return $params['api_key'];
            }
        };

        // Agent passes literal reference in params
        $output = $tool->executeTestAction(new Request([
            'resource_id' => $resource->id,
            'params' => [
                'api_key' => '[ENCRYPTED_REFERENCE:api_key]',
            ],
        ]));

        $this->assertEquals('very-secret-value', $output);
    }

    public function test_base_resource_tool_can_resolve_literal_encrypted_reference_with_extra_text(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $type = ResourceType::create([
            'name' => 'Secure Type',
            'config' => [['name' => 'api_key', 'type' => 'text', 'encrypted' => true]],
        ]);

        $resource = Resource::create([
            'resource_type_id' => $type->id,
            'name' => 'My Secure Resource',
            'user_id' => $user->id,
            'data' => ['api_key' => 'very-secret-value'],
        ]);

        $tool = new class extends BaseResourceTool
        {
            public function description(): string
            {
                return 'test tool';
            }

            public function schema(JsonSchema $schema): array
            {
                return [];
            }

            protected function requiredMappingFields(): array
            {
                return ['key' => 'API Key'];
            }

            public function executeTestAction(Request $request)
            {
                // Simulate ActionExecution binding like in real tools to ensure redaction is active
                $agent = \Mockery::mock(Agent::class);
                app()->instance(ActionExecution::class, new ActionExecution($agent, $this, $request));

                $resource = $this->getResource($request);
                $mapped = $this->mapResourceData($resource, $request);

                return $mapped['key'];
            }
        };

        // Agent passes reference WITH extra text (how it was before)
        $output = $tool->executeTestAction(new Request([
            'resource_id' => $resource->id,
            'resource_mapping' => [
                'key' => '[ENCRYPTED_REFERENCE:api_key] (Resolution happens automatically in tools)',
            ],
        ]));

        $this->assertEquals('very-secret-value', $output);
    }
}
