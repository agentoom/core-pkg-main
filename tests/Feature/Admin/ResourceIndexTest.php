<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Livewire\Admin\ResourceIndex;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Models\User;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class ResourceIndexTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected $resourceType;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin']);
        Role::create(['name' => 'superadmin']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->resourceType = ResourceType::factory()->create([
            'name' => ['en' => 'Test Type'],
        ]);
    }

    public function test_can_search_resources()
    {
        Resource::factory()->create([
            'name' => 'Apple',
            'description' => 'Red fruit',
            'resource_type_id' => $this->resourceType->id,
            'user_id' => $this->admin->id,
        ]);

        Resource::factory()->create([
            'name' => 'Banana',
            'description' => 'Yellow fruit',
            'resource_type_id' => $this->resourceType->id,
            'user_id' => $this->admin->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ResourceIndex::class)
            ->set('search', 'Apple')
            ->assertSee('Apple')
            ->assertDontSee('Banana');

        Livewire::actingAs($this->admin)
            ->test(ResourceIndex::class)
            ->set('search', 'Yellow')
            ->assertSee('Banana')
            ->assertDontSee('Apple');
    }

    public function test_can_filter_by_resource_type()
    {
        $otherType = ResourceType::factory()->create(['name' => ['en' => 'Other Type']]);

        Resource::factory()->create([
            'name' => 'Resource A',
            'resource_type_id' => $this->resourceType->id,
            'user_id' => $this->admin->id,
        ]);

        Resource::factory()->create([
            'name' => 'Resource B',
            'resource_type_id' => $otherType->id,
            'user_id' => $this->admin->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ResourceIndex::class)
            ->set('resource_type_id', $this->resourceType->id)
            ->assertSee('Resource A')
            ->assertDontSee('Resource B');
    }

    public function test_can_sort_resources()
    {
        $typeA = ResourceType::factory()->create(['name' => ['en' => 'Apple Type']]);
        $typeB = ResourceType::factory()->create(['name' => ['en' => 'Banana Type']]);

        Resource::factory()->create([
            'name' => 'Zebra',
            'resource_type_id' => $typeA->id,
            'user_id' => $this->admin->id,
            'created_at' => now()->subDay(),
        ]);

        Resource::factory()->create([
            'name' => 'Ant',
            'resource_type_id' => $typeB->id,
            'user_id' => $this->admin->id,
            'created_at' => now(),
        ]);

        // Default sort is name asc
        Livewire::actingAs($this->admin)
            ->test(ResourceIndex::class)
            ->assertSeeInOrder(['Ant', 'Zebra']);

        // Sort by name desc
        Livewire::actingAs($this->admin)
            ->test(ResourceIndex::class)
            ->call('sort', 'name') // Toggle to desc
            ->assertSeeInOrder(['Zebra', 'Ant']);

        // Sort by created_at asc
        Livewire::actingAs($this->admin)
            ->test(ResourceIndex::class)
            ->set('sortBy', 'created_at')
            ->set('sortDirection', 'asc')
            ->assertSeeInOrder(['Zebra', 'Ant']);

        // Sort by resource_type (joins resource_types and sorts by its name)
        Livewire::actingAs($this->admin)
            ->test(ResourceIndex::class)
            ->set('sortBy', 'resource_type')
            ->set('sortDirection', 'asc')
            ->assertSeeInOrder(['Zebra', 'Ant']); // Zebra has 'Apple Type', Ant has 'Banana Type'

        // Sort by owner (joins users and sorts by name)
        $userA = User::factory()->create(['name' => 'Adam']);
        $userB = User::factory()->create(['name' => 'Eve']);

        Resource::factory()->create([
            'name' => 'Resource 1',
            'user_id' => $userA->id,
            'resource_type_id' => $this->resourceType->id,
        ]);

        Resource::factory()->create([
            'name' => 'Resource 2',
            'user_id' => $userB->id,
            'resource_type_id' => $this->resourceType->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ResourceIndex::class)
            ->set('sortBy', 'owner')
            ->set('sortDirection', 'asc')
            ->assertSeeInOrder(['Resource 1', 'Resource 2']);
    }
}
