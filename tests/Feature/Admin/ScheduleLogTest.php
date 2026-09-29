<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Models\Schedule;
use Agentoom\Core\Models\ScheduleLog;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class ScheduleLogTest extends TestCase
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

    public function test_can_see_schedule_logs(): void
    {
        $schedule = Schedule::factory()->create();
        ScheduleLog::create([
            'schedule_id' => $schedule->id,
            'status' => 'success',
            'log' => 'Execution successful',
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.schedules.logs', $schedule))
            ->assertStatus(200)
            ->assertSee($schedule->name)
            ->assertSee('Execution successful');
    }

    public function test_can_filter_logs_by_status(): void
    {
        $schedule = Schedule::factory()->create();
        ScheduleLog::create([
            'schedule_id' => $schedule->id,
            'status' => 'success',
            'log' => 'Success Log',
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);
        ScheduleLog::create([
            'schedule_id' => $schedule->id,
            'status' => 'failed',
            'log' => 'Failed Log',
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);

        Livewire::actingAs($this->admin)
            ->test('agentoom-core::admin.schedule-log-index', ['schedule' => $schedule])
            ->assertSee('Success Log')
            ->assertSee('Failed Log')
            ->set('status', 'success')
            ->assertSee('Success Log')
            ->assertDontSee('Failed Log');
    }

    public function test_unauthorized_user_cannot_see_logs(): void
    {
        $otherUser = User::factory()->create();
        $schedule = Schedule::factory()->create(['user_id' => $this->admin->id]);

        $this->actingAs($otherUser)
            ->get(route('admin.schedules.logs', $schedule))
            ->assertStatus(403);
    }

    public function test_can_clear_schedule_logs(): void
    {
        $schedule = Schedule::factory()->create();
        ScheduleLog::create([
            'schedule_id' => $schedule->id,
            'status' => 'success',
            'log' => 'Success Log',
        ]);

        $this->assertEquals(1, $schedule->logs()->count());

        Livewire::actingAs($this->admin)
            ->test('agentoom-core::admin.schedule-log-index', ['schedule' => $schedule])
            ->call('clearLogs')
            ->assertStatus(200);

        $this->assertEquals(0, $schedule->logs()->count());
    }

    public function test_can_truncate_all_logs(): void
    {
        $schedule1 = Schedule::factory()->create();
        $schedule2 = Schedule::factory()->create();

        ScheduleLog::create(['schedule_id' => $schedule1->id, 'status' => 'success', 'log' => 'Log 1']);
        ScheduleLog::create(['schedule_id' => $schedule2->id, 'status' => 'success', 'log' => 'Log 2']);

        $this->assertEquals(2, ScheduleLog::count());

        Livewire::actingAs($this->admin)
            ->test('agentoom-core::admin.schedule-index')
            ->call('truncateLogs')
            ->assertStatus(200);

        $this->assertEquals(0, ScheduleLog::count());
    }

    public function test_admin_can_truncate_all_logs(): void
    {
        $admin = User::factory()->create();
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $admin->assignRole('admin');

        ScheduleLog::create(['schedule_id' => Schedule::factory()->create()->id, 'status' => 'success', 'log' => 'Log']);

        $this->assertEquals(1, ScheduleLog::count());

        Livewire::actingAs($admin)
            ->test('agentoom-core::admin.schedule-index')
            ->call('truncateLogs')
            ->assertStatus(200);

        $this->assertEquals(0, ScheduleLog::count());
    }

    public function test_non_admin_cannot_truncate_logs(): void
    {
        $user = User::factory()->create();
        $schedule = Schedule::factory()->create(['user_id' => $user->id]);

        ScheduleLog::create(['schedule_id' => $schedule->id, 'status' => 'success', 'log' => 'Log']);

        Livewire::actingAs($user)
            ->test('agentoom-core::admin.schedule-index')
            ->assertDontSee(__('agentoom-core::admin.schedules.truncate_logs'))
            ->call('truncateLogs')
            ->assertStatus(403);

        $this->assertEquals(1, ScheduleLog::count());
    }

    public function test_admin_and_superadmin_can_see_truncate_button(): void
    {
        // Superadmin
        Livewire::actingAs($this->admin)
            ->test('agentoom-core::admin.schedule-index')
            ->assertSee(__('agentoom-core::admin.schedules.truncate_logs'));

        // Admin
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));
        Livewire::actingAs($admin)
            ->test('agentoom-core::admin.schedule-index')
            ->assertSee(__('agentoom-core::admin.schedules.truncate_logs'));
    }
}
