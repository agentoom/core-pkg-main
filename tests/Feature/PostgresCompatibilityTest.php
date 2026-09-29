<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PostgresCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_find_resource_type_by_name_when_stored_as_json(): void
    {
        // Create a ResourceType with translatable name
        $resourceType = ResourceType::create([
            'name' => ['en' => 'Database', 'it' => 'Database it'],
            'config' => [],
        ]);

        // Standard whereIn fails on JSON columns with strings
        $found = ResourceType::whereIn('name', ['Database'])->first();
        $this->assertNull($found);

        // New scopes succeed
        $foundByScope = ResourceType::whereNameIn(['Database'])->first();
        $this->assertNotNull($foundByScope);
        $this->assertEquals($resourceType->id, $foundByScope->id);

        $foundBySingleName = ResourceType::whereName('Database it')->first();
        $this->assertNotNull($foundBySingleName);
        $this->assertEquals($resourceType->id, $foundBySingleName->id);

        $foundByLike = ResourceType::whereNameLike('%Data%')->first();
        $this->assertNotNull($foundByLike);
        $this->assertEquals($resourceType->id, $foundByLike->id);
    }

    public function test_can_find_resource_type_when_stored_as_string_in_json_column(): void
    {
        $resourceType = ResourceType::create([
            'name' => 'Legacy Name',
            'config' => [],
        ]);

        $found = ResourceType::where('name', 'Legacy Name')->first();
        $this->assertNotNull($found);
    }
}
