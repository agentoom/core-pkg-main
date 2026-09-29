<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Livewire\Admin\ResourceGroupCreateEdit;
use Agentoom\Core\Models\Language;
use Agentoom\Core\Models\ResourceGroup;
use Agentoom\Core\Models\User;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class ResourceGroupCreateEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Role::where('name', 'superadmin')->exists()) {
            Role::create(['name' => 'superadmin']);
        }
        Language::factory()->create(['code' => 'en', 'name' => 'English', 'is_active' => true]);
    }

    public function test_can_create_resource_group(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        Livewire::actingAs($user)
            ->test(ResourceGroupCreateEdit::class)
            ->set('names.en', 'Unique Test Group Name')
            ->set('icon', 'academic-cap')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.resource-groups.index'));

        $this->assertDatabaseHas('resource_groups', [
            'icon' => 'academic-cap',
        ]);

        $group = ResourceGroup::where('icon', 'academic-cap')->first();
        $this->assertEquals('Unique Test Group Name', $group->name['en']);
    }

    public function test_can_update_icon_via_event(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        Livewire::actingAs($user)
            ->test(ResourceGroupCreateEdit::class)
            ->dispatch('icon-selected', icon: 'key')
            ->assertSet('icon', 'key');
    }
}
