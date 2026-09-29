<?php

namespace Agentoom\Core\Tests\Feature\Ai\Tools\Public;

use Agentoom\Core\Ai\Tools\Public\ManageResourcesTool;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Models\User;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

class ManageResourcesToolJsonIssueTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_resource_with_nested_object_data(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $type = ResourceType::create([
            'name' => ['en' => 'Test Type'],
            'config' => [
                'fields' => [
                    ['name' => 'competitor_7', 'type' => 'text'],
                ],
            ],
            'user_id' => $user->id,
        ]);

        $tool = new ManageResourcesTool;

        $nestedData = [
            'competitor_name' => 'Competitor 7',
            'ranking_position' => 7,
        ];

        $request = new Request([
            'action' => 'create_resource',
            'resource_type_id' => $type->id,
            'name' => 'Test Resource',
            'data' => [
                'competitor_7' => $nestedData,
            ],
        ]);

        $response = $tool->handle($request);

        $this->assertStringContainsString('Successfully created resource', $response);

        $resource = Resource::first();
        $this->assertNotNull($resource);

        // Check if data is stored correctly.
        // It should now be a JSON STRING in the data array, because the field type is 'text'
        $this->assertIsArray($resource->data);
        $this->assertIsString($resource->data['competitor_7']);
        $this->assertEquals(json_encode($nestedData), $resource->data['competitor_7']);

        // Let's check the raw value in the database
        $raw = \DB::table('resources')->where('id', $resource->id)->value('data');
        // The raw JSON in the DB should have the nested value as a escaped string
        $this->assertStringContainsString('"competitor_7":"{\\"competitor_name\\":\\"Competitor 7\\"', $raw);
    }
}
