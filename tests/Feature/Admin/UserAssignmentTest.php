<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class UserAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'superadmin']);
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'user']);
    }

    public function test_superadmin_can_assign_anyone(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $user = User::factory()->create();
        $user->assignRole('user');

        $assignable = $superadmin->getAssignableUsers();

        $this->assertTrue($assignable->contains($superadmin));
        $this->assertTrue($assignable->contains($admin));
        $this->assertTrue($assignable->contains($user));
    }

    public function test_admin_cannot_assign_superadmin(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $user = User::factory()->create();
        $user->assignRole('user');

        $assignable = $admin->getAssignableUsers();

        $this->assertTrue($assignable->contains($admin));
        $this->assertTrue($assignable->contains($user));
        $this->assertFalse($assignable->contains($superadmin));
    }

    public function test_user_can_only_assign_himself(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $user = User::factory()->create();
        $user->assignRole('user');

        $assignable = $user->getAssignableUsers();

        $this->assertTrue($assignable->contains($user));
        $this->assertFalse($assignable->contains($superadmin));
        $this->assertFalse($assignable->contains($admin));
        $this->assertCount(1, $assignable);
    }

    public function test_assignable_users_filters_out_system_users(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $systemUser = User::factory()->create(['is_system' => true]);

        $assignable = $superadmin->getAssignableUsers();

        $this->assertFalse($assignable->contains($systemUser));
    }

    public function test_schedule_validation_enforces_assignment_rules(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $user = User::factory()->create();
        $user->assignRole('user');

        // Admin trying to assign to superadmin should fail
        Livewire::actingAs($admin)
            ->test('agentoom-core::admin.schedule-create-edit')
            ->set('name', 'Test Schedule')
            ->set('user_id', $superadmin->id)
            ->call('save')
            ->assertHasErrors(['user_id']);

        // Admin trying to assign to user should succeed
        Livewire::actingAs($admin)
            ->test('agentoom-core::admin.schedule-create-edit')
            ->set('name', 'Test Schedule')
            ->set('minute', '*')
            ->set('hour', '*')
            ->set('day', '*')
            ->set('month', '*')
            ->set('day_of_week', '*')
            ->set('timezone', 'UTC')
            ->set('target_type', 'custom')
            ->set('model', 'Agentoom\Core\Models\Agent')
            ->set('model_id', 1)
            ->set('user_id', $user->id)
            ->call('save')
            ->assertHasNoErrors(['user_id']);

        // User trying to assign to admin should fail
        Livewire::actingAs($user)
            ->test('agentoom-core::admin.schedule-create-edit')
            ->set('name', 'Test Schedule User')
            ->set('user_id', $admin->id)
            ->call('save')
            ->assertHasErrors(['user_id']);

        // User trying to assign to himself should succeed
        Livewire::actingAs($user)
            ->test('agentoom-core::admin.schedule-create-edit')
            ->set('name', 'Test Schedule User Correct')
            ->set('minute', '*')
            ->set('hour', '*')
            ->set('day', '*')
            ->set('month', '*')
            ->set('day_of_week', '*')
            ->set('timezone', 'UTC')
            ->set('target_type', 'custom')
            ->set('model', 'Agentoom\Core\Models\Agent')
            ->set('model_id', 1)
            ->set('user_id', $user->id)
            ->call('save')
            ->assertHasNoErrors(['user_id']);
    }
}
