<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Tools\BaseResourceTool;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

class BaseResourceToolRobustnessTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_handles_stringified_json_mapping()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $type = ResourceType::create([
            'name' => 'Test Type',
            'config' => [['name' => 'api_key', 'type' => 'text', 'encrypted' => true]],
        ]);

        $resource = Resource::create([
            'resource_type_id' => $type->id,
            'name' => 'Test Resource',
            'data' => ['api_key' => 'secret-123'],
        ]);

        $tool = new class extends BaseResourceTool
        {
            public function requiredMappingFields(): array
            {
                return ['key' => 'The API key'];
            }

            public function handle(Request $request): string
            {
                $resource = $this->getResource($request);
                $mapped = $this->mapResourceData($resource, $request);

                return $mapped['key'] ?? 'missing';
            }

            public function schema(JsonSchema $schema): array
            {
                return [];
            }

            public function description(): string
            {
                return 'test';
            }
        };

        // Mimic the agent sending a JSON string for resource_mapping
        $mappingJson = json_encode(['key' => 'data.api_key']);
        $request = new Request([
            'resource_id' => (string) $resource->id,
            'resource_mapping' => $mappingJson,
        ]);

        $result = $tool->handle($request);

        $this->assertEquals('secret-123', $result);
    }

    public function test_it_handles_omitted_data_prefix()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $type = ResourceType::create([
            'name' => 'Test Type',
            'config' => [['name' => 'api_key', 'type' => 'text', 'encrypted' => true]],
        ]);

        $resource = Resource::create([
            'resource_type_id' => $type->id,
            'name' => 'Test Resource',
            'data' => ['api_key' => 'secret-123'],
        ]);

        $tool = new class extends BaseResourceTool
        {
            public function requiredMappingFields(): array
            {
                return ['key' => 'The API key'];
            }

            public function handle(Request $request): string
            {
                $resource = $this->getResource($request);
                $mapped = $this->mapResourceData($resource, $request);

                return $mapped['key'] ?? 'missing';
            }

            public function schema(JsonSchema $schema): array
            {
                return [];
            }

            public function description(): string
            {
                return 'test';
            }
        };

        // Omit 'data.' prefix
        $request = new Request([
            'resource_id' => (string) $resource->id,
            'resource_mapping' => ['key' => 'api_key'],
        ]);

        $result = $tool->handle($request);

        $this->assertEquals('secret-123', $result);
    }
}
