<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Livewire\Admin\UserMemoryIndex;
use Agentoom\Core\Models\UserInformation;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class UserMemoryIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
    }

    public function test_admin_can_create_edit_delete_and_search_user_memories(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('superadmin');
        $user = User::factory()->create();
        $this->actingAs($admin);
        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee(__('agentoom-core::admin.users.memories'));

        $this->actingAs($admin)->get(route('admin.users.memories', $user))->assertOk();

        Livewire::test(UserMemoryIndex::class, ['user' => $user])
            ->call('createMemory')
            ->set('title', 'Preferred name')
            ->set('content', 'Call the user Ernesto.')
            ->set('importance', 5)
            ->set('expires_at', '2099-01-01')
            ->call('saveMemory')
            ->assertHasNoErrors()
            ->set('search', 'Ernesto')
            ->assertSee('Preferred name')
            ->assertSee('Call the user Ernesto.');

        $memory = UserInformation::query()->where('user_id', $user->id)->firstOrFail();

        Livewire::test(UserMemoryIndex::class, ['user' => $user])
            ->call('editMemory', $memory)
            ->set('content', 'Always address the user as Ernesto.')
            ->call('saveMemory')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('user_information', [
            'id' => $memory->id,
            'user_id' => $user->id,
            'title' => 'Preferred name',
            'content' => 'Always address the user as Ernesto.',
            'importance' => 5,
        ]);

        Livewire::test(UserMemoryIndex::class, ['user' => $user])
            ->call('confirmDeleteMemory', $memory)
            ->call('deleteMemory')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('user_information', ['id' => $memory->id]);
    }
}
