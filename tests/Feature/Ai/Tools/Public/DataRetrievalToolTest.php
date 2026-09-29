<?php

namespace Agentoom\Core\Tests\Feature\Ai\Tools\Public;

use Agentoom\Core\Ai\Tools\Public\DataRetrievalTool;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceGroup;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Models\User;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Spatie\Permission\Models\Role;

class DataRetrievalToolTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Role::where('name', 'admin')->exists()) {
            Role::create(['name' => 'admin']);
        }
    }

    public function test_can_get_resource_groups(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        ResourceGroup::factory()->create(['name' => ['en' => 'Group A']]);
        ResourceGroup::factory()->create(['name' => ['en' => 'Group B']]);

        $tool = new DataRetrievalTool;
        $output = $tool->handle(new Request(['action' => 'get_resource_groups']));

        $this->assertStringContainsString('Group A', $output);
        $this->assertStringContainsString('Group B', $output);
    }

    public function test_can_get_resource_types_optionally_filtered_by_group(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $group = ResourceGroup::factory()->create(['name' => ['en' => 'Group A']]);
        $type1 = ResourceType::factory()->create([
            'name' => ['en' => 'Type 1'],
            'resource_group_id' => $group->id,
        ]);
        $type2 = ResourceType::factory()->create([
            'name' => ['en' => 'Type 2'],
            'resource_group_id' => null,
        ]);

        $tool = new DataRetrievalTool;

        // All types
        $output = $tool->handle(new Request(['action' => 'get_resource_types']));
        $this->assertStringContainsString('Type 1', $output);
        $this->assertStringContainsString('Type 2', $output);

        // Filtered by group
        $output = $tool->handle(new Request([
            'action' => 'get_resource_types',
            'resource_group_id' => $group->id,
        ]));
        $this->assertStringContainsString('Type 1', $output);
        $this->assertStringNotContainsString('Type 2', $output);
    }

    public function test_can_get_resources_with_filters(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $type = ResourceType::factory()->create(['name' => ['en' => 'Type A']]);
        Resource::factory()->create([
            'name' => 'Resource 1',
            'resource_type_id' => $type->id,
            'user_id' => $user->id,
            'data' => ['price' => 100],
        ]);
        Resource::factory()->create([
            'name' => 'Resource 2',
            'resource_type_id' => $type->id,
            'user_id' => $user->id,
            'data' => ['price' => 200],
        ]);

        $tool = new DataRetrievalTool;

        $output = $tool->handle(new Request([
            'action' => 'get_resources',
            'resource_type_id' => $type->id,
            'filters' => ['data.price > 150'],
        ]));

        $this->assertStringContainsString('Resource 2', $output);
        $this->assertStringNotContainsString('Resource 1', $output);
    }
}
