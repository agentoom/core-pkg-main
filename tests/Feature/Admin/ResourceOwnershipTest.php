<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class ResourceOwnershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'superadmin']);
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'user']);
    }

    public function test_user_can_only_see_their_own_or_assigned_resources()
    {
        $user1 = User::factory()->create();
        $user1->assignRole('user');
        $user2 = User::factory()->create();
        $user2->assignRole('user');

        $type = ResourceType::create(['name' => 'Type A', 'config' => []]);

        $resource1 = Resource::create([
            'resource_type_id' => $type->id,
            'user_id' => $user1->id,
            'name' => 'User 1 Resource',
            'data' => [],
        ]);

        $resource2 = Resource::create([
            'resource_type_id' => $type->id,
            'user_id' => $user2->id,
            'name' => 'User 2 Resource',
            'data' => [],
        ]);

        $visibleResources = Resource::visibleTo($user1)->get();

        $this->assertTrue($visibleResources->contains($resource1));
        $this->assertFalse($visibleResources->contains($resource2));
    }

    public function test_admin_can_see_all_resources()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $user = User::factory()->create();
        $user->assignRole('user');

        $type = ResourceType::create(['name' => 'Type A', 'config' => []]);

        $resource = Resource::create([
            'resource_type_id' => $type->id,
            'user_id' => $user->id,
            'name' => 'User Resource',
            'data' => [],
        ]);

        $this->actingAs($admin);

        $visibleResources = Resource::visibleTo($admin)->get();

        $this->assertTrue($visibleResources->contains($resource));
    }

    public function test_user_cannot_create_more_than_one_resource_of_same_type()
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $type = ResourceType::create(['name' => 'Type A', 'config' => []]);

        Resource::create([
            'resource_type_id' => $type->id,
            'user_id' => $user->id,
            'name' => 'Existing Resource',
            'data' => [],
        ]);

        Livewire::actingAs($user)
            ->test('agentoom-core::admin.resource-create-edit', ['resource' => null])
            ->set('resource_type_id', $type->id)
            ->set('name', 'Second Resource')
            ->call('save')
            ->assertHasErrors(['resource_type_id']);
    }

    public function test_admin_can_create_multiple_resources_of_same_type()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $type = ResourceType::create(['name' => 'Type A', 'config' => []]);

        Resource::create([
            'resource_type_id' => $type->id,
            'user_id' => $admin->id,
            'name' => 'Admin Resource 1',
            'data' => [],
        ]);

        Livewire::actingAs($admin)
            ->test('agentoom-core::admin.resource-create-edit', ['resource' => null])
            ->set('resource_type_id', $type->id)
            ->set('name', 'Admin Resource 2')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(2, Resource::count());
    }

    public function test_user_cannot_edit_resource_they_dont_own()
    {
        $user1 = User::factory()->create();
        $user1->assignRole('user');
        $user2 = User::factory()->create();
        $user2->assignRole('user');

        $type = ResourceType::create(['name' => 'Type A', 'config' => []]);

        $resource2 = Resource::create([
            'resource_type_id' => $type->id,
            'user_id' => $user2->id,
            'name' => 'User 2 Resource',
            'data' => [],
        ]);

        Livewire::actingAs($user1)
            ->test('agentoom-core::admin.resource-create-edit', ['resource' => $resource2])
            ->assertForbidden();
    }
}
