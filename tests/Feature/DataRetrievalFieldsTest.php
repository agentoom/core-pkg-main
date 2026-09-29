<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Tools\Public\DataRetrievalTool;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Models\User;
use Agentoom\Core\Services\DataRetrievalService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

class DataRetrievalFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->actingAs($user);
    }

    public function test_it_retrieves_only_specified_fields_for_resources(): void
    {
        $type = ResourceType::create(['name' => 'Type', 'config' => []]);
        Resource::create([
            'resource_type_id' => $type->id,
            'name' => 'Res 1',
            'data' => ['price' => 50],
            'description' => 'A description',
        ]);

        $service = new DataRetrievalService;

        // Test with specific fields
        $results = $service->getResources(fields: ['id', 'name']);

        $this->assertCount(1, $results);
        $this->assertArrayHasKey('id', $results[0]);
        $this->assertArrayHasKey('name', $results[0]);
        $this->assertArrayNotHasKey('data', $results[0]);
        $this->assertArrayNotHasKey('description', $results[0]);
        $this->assertArrayNotHasKey('resource_type_id', $results[0]);

        // Test with all fields (empty fields array)
        $results = $service->getResources(fields: []);
        $this->assertArrayHasKey('data', $results[0]);
        $this->assertArrayHasKey('description', $results[0]);
    }

    public function test_it_retrieves_only_specified_fields_for_scraped_data(): void
    {
        $dataType = ResourceType::create(['name' => 'Type', 'config' => []]);
        Resource::create([
            'resource_type_id' => $dataType->id,
            'user_id' => auth()->id(),
            'name' => 'Example 1',
            'data' => [
                'url' => 'https://example.com/1',
                'title' => 'Example 1',
                'rating' => 5,
            ],
        ]);

        $service = new DataRetrievalService;

        // Test with specific fields (note: we use the new cleaner aliasing logic)
        $results = $service->getScrapedData(fields: ['id', 'data.url']);

        $this->assertCount(1, $results);
        $this->assertArrayHasKey('id', $results[0]);
        $this->assertArrayHasKey('j-url', $results[0]); // was j-data_url
        $this->assertArrayNotHasKey('name', $results[0]);
        $this->assertArrayNotHasKey('data', $results[0]);

        // Test with all fields
        $results = $service->getScrapedData(fields: []);
        $this->assertArrayHasKey('name', $results[0]);
        $this->assertArrayHasKey('data', $results[0]);
    }

    public function test_tool_can_retrieve_specific_fields(): void
    {
        $type = ResourceType::create(['name' => 'Type', 'config' => []]);
        Resource::create(['resource_type_id' => $type->id, 'name' => 'Res 1', 'data' => ['price' => 50]]);

        $tool = new DataRetrievalTool;
        $request = new Request([
            'action' => 'get_resources',
            'fields' => ['id', 'name'],
        ]);

        $output = $tool->handle($request);

        $this->assertStringContainsString('Found 1 Resources', $output);
        $this->assertStringContainsString('name: Res 1', $output);
        $this->assertStringNotContainsString('data:', $output);
    }

    public function test_json_fields_preserve_names_with_prefix(): void
    {
        $type = ResourceType::create(['name' => 'Type', 'config' => []]);
        Resource::create([
            'resource_type_id' => $type->id,
            'name' => 'Res 1',
            'data' => ['price' => 50, 'nested' => ['field' => 'value']],
        ]);

        $service = new DataRetrievalService;

        // Test with manual JSON path
        $results = $service->getResources(fields: ['data->price']);
        $this->assertCount(1, $results);
        $this->assertArrayHasKey('j-price', $results[0]);
        $this->assertEquals(50, $results[0]['j-price']);

        // Test with field name (should resolve to data->price and alias to j-price)
        $results2 = $service->getResources(fields: ['price']);
        $this->assertCount(1, $results2);
        $this->assertArrayHasKey('j-price', $results2[0]);
        $this->assertEquals(50, $results2[0]['j-price']);

        // Test with nested dot notation
        $results3 = $service->getResources(fields: ['nested.field']);
        $this->assertCount(1, $results3);
        $this->assertArrayHasKey('j-nested_field', $results3[0]);
        $this->assertEquals('value', $results3[0]['j-nested_field']);

        // Test with standard column (id)
        $results4 = $service->getResources(fields: ['id', 'price']);
        $this->assertCount(1, $results4);
        $this->assertArrayHasKey('id', $results4[0]);
        $this->assertArrayHasKey('j-price', $results4[0]);
    }
}
