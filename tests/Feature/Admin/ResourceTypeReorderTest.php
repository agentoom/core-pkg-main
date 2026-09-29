<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Livewire\Admin\ResourceTypeCreateEdit;
use Agentoom\Core\Models\Language;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Models\User;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class ResourceTypeReorderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Language::factory()->create(['code' => 'en', 'is_active' => true]);
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
    }

    public function test_can_reorder_fields_in_resource_type()
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');
        $this->actingAs($user);

        $resourceType = ResourceType::factory()->create([
            'config' => [
                ['name' => 'field1', 'type' => 'text'],
                ['name' => 'field2', 'type' => 'text'],
                ['name' => 'field3', 'type' => 'text'],
            ],
        ]);

        Livewire::test(ResourceTypeCreateEdit::class, ['resourceType' => $resourceType])
            ->assertSet('config.0.name', 'field1')
            ->assertSet('config.1.name', 'field2')
            ->assertSet('config.2.name', 'field3')
            ->call('moveFieldDown', 0)
            ->assertSet('config.0.name', 'field2')
            ->assertSet('config.1.name', 'field1')
            ->assertSet('config.2.name', 'field3')
            ->call('moveFieldUp', 2)
            ->assertSet('config.0.name', 'field2')
            ->assertSet('config.1.name', 'field3')
            ->assertSet('config.2.name', 'field1');
    }
}
