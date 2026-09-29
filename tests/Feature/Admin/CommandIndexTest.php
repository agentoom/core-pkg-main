<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class CommandIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
    }

    public function test_superadmin_can_access_command_index_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        $this->actingAs($user)
            ->get(route('admin.configs.commands'))
            ->assertStatus(200)
            ->assertSee(__('agentoom-core::admin.configs.section_console_commands'));
    }

    public function test_non_superadmin_cannot_access_command_index_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.configs.commands'))
            ->assertStatus(403);
    }

    public function test_non_superadmin_cannot_execute_artisan_command(): void
    {
        Artisan::spy();

        $user = User::factory()->create();

        Livewire::actingAs($user, 'web')
            ->test('agentoom-core::admin.command-index')
            ->call('executeCommand', 'agentoom:analyze-memory')
            ->assertForbidden();

        Artisan::shouldNotHaveReceived('call');
    }
}
