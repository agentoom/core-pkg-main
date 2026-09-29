<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class ResourceCreateEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Role::where('name', 'superadmin')->exists()) {
            Role::create(['name' => 'superadmin']);
        }
        if (! Role::where('name', 'admin')->exists()) {
            Role::create(['name' => 'admin']);
        }
        if (! Role::where('name', 'user')->exists()) {
            Role::create(['name' => 'user']);
        }
    }

    public function test_component_can_handle_keyed_config_fields()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $type = ResourceType::create([
            'name' => 'Forge',
            'config' => [
                'fields' => [
                    ['name' => 'api_token', 'type' => 'password', 'label' => 'API Token', 'required' => true],
                ],
            ],
        ]);

        Livewire::actingAs($admin)
            ->test('agentoom-core::admin.resource-create-edit', ['resource' => null])
            ->set('resource_type_id', $type->id)
            ->assertSet('data', ['api_token' => ''])
            ->set('name', 'Test Forge')
            ->set('data.api_token', 'secret-token')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.resources.index'));

        $this->assertEquals(1, Resource::count());
        $resource = Resource::first();
        $this->assertEquals('secret-token', $resource->data['api_token']);
    }

    public function test_component_can_handle_flat_config_fields()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $type = ResourceType::create([
            'name' => 'Legacy Type',
            'config' => [
                ['name' => 'old_field', 'type' => 'text', 'label' => 'Old Field', 'required' => false],
            ],
        ]);

        Livewire::actingAs($admin)
            ->test('agentoom-core::admin.resource-create-edit', ['resource' => null])
            ->set('resource_type_id', $type->id)
            ->assertSet('data', ['old_field' => ''])
            ->set('name', 'Test Legacy')
            ->set('data.old_field', 'some value')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(1, Resource::count());
        $resource = Resource::first();
        $this->assertEquals('some value', $resource->data['old_field']);
    }

    public function test_component_handles_checkbox_with_options()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $type = ResourceType::create([
            'name' => 'Checkbox Type',
            'config' => [
                'fields' => [
                    [
                        'name' => 'features',
                        'type' => 'checkbox',
                        'label' => 'Features',
                        'options' => ['Option 1', 'Option 2'],
                    ],
                ],
            ],
        ]);

        Livewire::actingAs($admin)
            ->test('agentoom-core::admin.resource-create-edit', ['resource' => null])
            ->set('resource_type_id', $type->id)
            ->assertSet('data', ['features' => []])
            ->set('name', 'Test Checkbox')
            ->set('data.features', ['Option 1'])
            ->call('save')
            ->assertHasNoErrors();

        $resource = Resource::first();
        $this->assertEquals(['Option 1'], $resource->data['features']);
    }
}
