<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Tools\Public\DataRetrievalTool;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Models\User;
use Agentoom\Core\Services\ActionDiscoveryService;
use Agentoom\Core\Services\DataRetrievalService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Ai\Tools\Request;

class DataRetrievalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->actingAs($user);
    }

    public function test_it_discovers_data_retrieval_actions(): void
    {
        $service = new ActionDiscoveryService;
        $methods = $service->discover();

        $resourceKey = 'Agentoom\Core\Services\DataRetrievalService@getResources';
        $scrapedKey = 'Agentoom\Core\Services\DataRetrievalService@getScrapedData';

        $this->assertArrayHasKey($resourceKey, $methods);
        $this->assertArrayHasKey($scrapedKey, $methods);

        $this->assertEquals('Get Resources', $methods[$resourceKey]['name']);
        $this->assertEquals('Get Scraped Data', $methods[$scrapedKey]['name']);
    }

    public function test_it_filters_resources_by_type(): void
    {
        $type1 = ResourceType::create(['name' => 'Type 1', 'config' => []]);
        $type2 = ResourceType::create(['name' => 'Type 2', 'config' => []]);

        Resource::create(['resource_type_id' => $type1->id, 'name' => 'Res 1', 'data' => []]);
        Resource::create(['resource_type_id' => $type2->id, 'name' => 'Res 2', 'data' => []]);

        $service = new DataRetrievalService;
        $results = $service->getResources(limit: 10, resource_type_id: $type1->id);

        $this->assertCount(1, $results);
        $this->assertEquals('Res 1', $results[0]['name']);
    }

    public function test_it_filters_resources_by_field_conditions(): void
    {
        $type = ResourceType::create(['name' => 'Type', 'config' => ['fields' => [['name' => 'price', 'type' => 'number'], ['name' => 'title', 'type' => 'string']]]]);

        Resource::create(['resource_type_id' => $type->id, 'name' => 'Cheap', 'data' => ['price' => 50]]);
        Resource::create(['resource_type_id' => $type->id, 'name' => 'Expensive', 'data' => ['price' => 150]]);
        Resource::create(['resource_type_id' => $type->id, 'name' => 'Laravel Book', 'data' => ['title' => 'Learning Laravel']]);

        $service = new DataRetrievalService;

        // Test > operator
        $results = $service->getResources(filters: ['price > 100']);
        $this->assertCount(1, $results);
        $this->assertEquals('Expensive', $results[0]['name']);

        // Test contains operator
        $results = $service->getResources(filters: ["title contains 'Laravel'"]);
        $this->assertCount(1, $results);
        $this->assertEquals('Laravel Book', $results[0]['name']);
    }

    public function test_it_filters_scraped_data_by_columns_and_data(): void
    {
        $dataType = ResourceType::create(['name' => 'Type', 'config' => []]);
        Resource::create([
            'resource_type_id' => $dataType->id,
            'user_id' => auth()->id(),
            'name' => 'Example 1',
            'data' => ['rating' => 5],
        ]);
        Resource::create([
            'resource_type_id' => $dataType->id,
            'user_id' => auth()->id(),
            'name' => 'Example 2',
            'data' => ['rating' => 3],
        ]);

        $service = new DataRetrievalService;

        // Test column filter (name is a column)
        $results = $service->getScrapedData(filters: ["name = 'Example 1'"]);
        $this->assertCount(1, $results);
        $this->assertEquals('Example 1', $results[0]['name']);

        // Test JSON data filter
        $results = $service->getScrapedData(filters: ['rating < 4']);
        $this->assertCount(1, $results);
        $this->assertEquals('Example 2', $results[0]['name']);
    }

    public function test_tool_can_retrieve_resources(): void
    {
        $type = ResourceType::create(['name' => 'Type', 'config' => []]);
        Resource::create(['resource_type_id' => $type->id, 'name' => 'Res 1', 'data' => ['price' => 50]]);

        $tool = new DataRetrievalTool;
        $request = new Request([
            'action' => 'get_resources',
            'filters' => ['price < 100'],
        ]);

        $output = (string) $tool->handle($request);

        $this->assertStringContainsString('Found 1 Resources', $output);
        $this->assertStringContainsString('Res 1', $output);
    }

    public function test_it_filters_resources_not_empty(): void
    {
        $type = ResourceType::create(['name' => 'Type', 'config' => []]);

        Resource::create(['resource_type_id' => $type->id, 'name' => 'Full', 'data' => ['custom_field' => 'value']]);
        Resource::create(['resource_type_id' => $type->id, 'name' => 'Empty String', 'data' => ['custom_field' => '']]);
        Resource::create(['resource_type_id' => $type->id, 'name' => 'Null', 'data' => ['custom_field' => null]]);
        Resource::create(['resource_type_id' => $type->id, 'name' => 'Missing', 'data' => []]);

        $service = new DataRetrievalService;

        // Test "is not empty"
        $results = $service->getResources(filters: ['custom_field is not empty']);

        $this->assertCount(1, $results);
        $this->assertEquals('Full', $results[0]['name']);

        // Test "is empty"
        $results = $service->getResources(filters: ['custom_field is empty']);
        $this->assertCount(3, $results); // Empty String, Null, Missing
    }

    public function test_it_filters_resources_older_than_7_days(): void
    {
        Carbon::setTestNow('2026-05-11 12:00:00');
        $type = ResourceType::create(['name' => 'Type', 'config' => []]);

        $old = new Resource(['resource_type_id' => $type->id, 'name' => 'Old', 'data' => []]);
        $old->created_at = Carbon::now()->subDays(10);
        $old->save();

        $new = new Resource(['resource_type_id' => $type->id, 'name' => 'New', 'data' => []]);
        $new->created_at = Carbon::now()->subDays(2);
        $new->save();

        $service = new DataRetrievalService;

        // Test relative date parsing
        $results = $service->getResources(filters: ["created_at < '7 days ago'"]);

        $this->assertCount(1, $results);
        $this->assertEquals('Old', $results[0]['name']);
    }

    public function test_it_filters_by_null_status(): void
    {
        $type = ResourceType::create(['name' => 'Type', 'config' => []]);

        Resource::create(['resource_type_id' => $type->id, 'name' => 'Not Null', 'data' => ['field' => 'val']]);
        Resource::create(['resource_type_id' => $type->id, 'name' => 'Null', 'data' => ['field' => null]]);

        $service = new DataRetrievalService;

        $results = $service->getResources(filters: ['field is null']);
        $this->assertCount(1, $results);
        $this->assertEquals('Null', $results[0]['name']);

        $results = $service->getResources(filters: ['field is not null']);
        $this->assertCount(1, $results);
        $this->assertEquals('Not Null', $results[0]['name']);
    }

    public function test_it_supports_or_operand_prefix(): void
    {
        $type = ResourceType::create(['name' => 'Type', 'config' => []]);

        Resource::create(['resource_type_id' => $type->id, 'name' => 'A', 'data' => ['val' => 1]]);
        Resource::create(['resource_type_id' => $type->id, 'name' => 'B', 'data' => ['val' => 2]]);
        Resource::create(['resource_type_id' => $type->id, 'name' => 'C', 'data' => ['val' => 3]]);

        $service = new DataRetrievalService;

        // val = 1 OR val = 3
        $results = $service->getResources(filters: ['val = 1', 'OR val = 3']);

        $this->assertCount(2, $results);
        $names = collect($results)->pluck('name')->toArray();
        $this->assertContains('A', $names);
        $this->assertContains('C', $names);
    }

    public function test_it_supports_grouped_logic_with_subarrays(): void
    {
        $type = ResourceType::create(['name' => 'Type', 'config' => []]);

        // (Age = 30 AND City = London) OR (Age = 25 AND City = New York)
        Resource::create(['resource_type_id' => $type->id, 'name' => 'R1', 'data' => ['age' => 30, 'city' => 'London']]);
        Resource::create(['resource_type_id' => $type->id, 'name' => 'R2', 'data' => ['age' => 25, 'city' => 'New York']]);
        Resource::create(['resource_type_id' => $type->id, 'name' => 'R3', 'data' => ['age' => 30, 'city' => 'New York']]);

        $service = new DataRetrievalService;

        $filters = [
            ['age = 30', 'city = "London"'],
            ['OR', 'age = 25', 'city = "New York"'],
        ];

        $results = $service->getResources(filters: $filters);

        $this->assertCount(2, $results);
        $names = collect($results)->pluck('name')->toArray();
        $this->assertContains('R1', $names);
        $this->assertContains('R2', $names);
        $this->assertNotContains('R3', $names);
    }

    public function test_it_supports_or_key_for_grouping(): void
    {
        $type = ResourceType::create(['name' => 'Type', 'config' => []]);

        Resource::create(['resource_type_id' => $type->id, 'name' => 'R1', 'data' => ['age' => 30, 'city' => 'London']]);
        Resource::create(['resource_type_id' => $type->id, 'name' => 'R2', 'data' => ['age' => 25, 'city' => 'New York']]);

        $service = new DataRetrievalService;

        $filters = [
            ['age = 30', 'city = "London"'],
            'or' => ['age = 25', 'city = "New York"'],
        ];

        $results = $service->getResources(filters: $filters);

        $this->assertCount(2, $results);
    }

    public function test_it_can_return_total_count_for_resources(): void
    {
        $type = ResourceType::create(['name' => 'Type', 'config' => []]);
        Resource::create(['resource_type_id' => $type->id, 'name' => 'Res 1', 'data' => ['price' => 50]]);
        Resource::create(['resource_type_id' => $type->id, 'name' => 'Res 2', 'data' => ['price' => 150]]);

        $service = new DataRetrievalService;

        $count = $service->getResources(countOnly: true);
        $this->assertEquals(2, $count);

        $count = $service->getResources(filters: ['price > 100'], countOnly: true);
        $this->assertEquals(1, $count);
    }

    public function test_it_can_return_total_count_for_scraped_data(): void
    {
        $dataType = ResourceType::create(['name' => 'Type', 'config' => []]);
        Resource::create(['resource_type_id' => $dataType->id, 'name' => 'Example 1', 'data' => [], 'user_id' => auth()->id()]);
        Resource::create(['resource_type_id' => $dataType->id, 'name' => 'Example 2', 'data' => [], 'user_id' => auth()->id()]);

        $service = new DataRetrievalService;

        $count = $service->getScrapedData(countOnly: true);
        $this->assertEquals(2, $count);

        $count = $service->getScrapedData(filters: ["name = 'Example 1'"], countOnly: true);
        $this->assertEquals(1, $count);
    }

    public function test_tool_can_return_total_count(): void
    {
        $type = ResourceType::create(['name' => 'Type', 'config' => []]);
        Resource::create(['resource_type_id' => $type->id, 'name' => 'Res 1', 'data' => ['price' => 50]]);
        Resource::create(['resource_type_id' => $type->id, 'name' => 'Res 2', 'data' => ['price' => 150]]);

        $tool = new DataRetrievalTool;
        $request = new Request([
            'action' => 'get_resources',
            'filters' => ['price > 0'],
            'count_only' => true,
        ]);

        $output = (string) $tool->handle($request);

        $this->assertStringContainsString('Total Resources found: 2', $output);
    }
}
