<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Schedule;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user
        $this->admin = User::factory()->create();
        Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $this->admin->assignRole('superadmin');
    }

    public function test_can_see_schedule_index(): void
    {
        Schedule::factory()->count(3)->create();

        $this->actingAs($this->admin)
            ->get(route('admin.schedules.index'))
            ->assertStatus(200)
            ->assertSee(__('agentoom-core::admin.schedules.title'));
    }

    public function test_can_create_schedule(): void
    {
        $agent = Agent::factory()->create();

        Livewire::actingAs($this->admin)
            ->test('agentoom-core::admin.schedule-create-edit')
            ->set('name', 'Test Schedule')
            ->set('user_id', $this->admin->id)
            ->set('model', Agent::class)
            ->set('model_id', $agent->id)
            ->set('minute', '0')
            ->set('hour', '12')
            ->set('instructions', 'Run some task')
            ->call('save')
            ->assertRedirect(route('admin.schedules.index'));

        $this->assertDatabaseHas('schedules', [
            'name' => 'Test Schedule',
            'model' => Agent::class,
            'model_id' => $agent->id,
            'minute' => '0',
            'hour' => '12',
            'instructions' => 'Run some task',
        ]);
    }

    public function test_can_edit_schedule(): void
    {
        $schedule = Schedule::factory()->create();

        Livewire::actingAs($this->admin)
            ->test('agentoom-core::admin.schedule-create-edit', ['schedule' => $schedule])
            ->set('name', 'Updated Name')
            ->call('save')
            ->assertRedirect(route('admin.schedules.index'));

        $this->assertEquals('Updated Name', $schedule->fresh()->name);
    }

    public function test_can_delete_schedule(): void
    {
        $schedule = Schedule::factory()->create();

        Livewire::actingAs($this->admin)
            ->test('agentoom-core::admin.schedule-index')
            ->call('showDeleteModal', $schedule->id)
            ->call('delete')
            ->assertStatus(200);

        $this->assertDatabaseMissing('schedules', ['id' => $schedule->id]);
    }

    public function test_can_toggle_active(): void
    {
        $schedule = Schedule::factory()->create(['active' => true]);

        Livewire::actingAs($this->admin)
            ->test('agentoom-core::admin.schedule-index')
            ->call('toggleActive', $schedule->id);

        $this->assertFalse($schedule->fresh()->active);
    }
}
