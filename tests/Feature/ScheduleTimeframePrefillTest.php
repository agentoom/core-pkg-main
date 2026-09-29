<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class ScheduleTimeframePrefillTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $this->admin->assignRole('superadmin');
    }

    public function test_it_prefills_every_5_minutes(): void
    {
        Livewire::actingAs($this->admin)
            ->test('agentoom-core::admin.schedule-create-edit')
            ->set('timeframe', 'every_5_minutes')
            ->assertSet('minute', '*/5')
            ->assertSet('hour', '*')
            ->assertSet('day', '*')
            ->assertSet('month', '*')
            ->assertSet('day_of_week', '*');
    }

    public function test_it_prefills_every_week(): void
    {
        Livewire::actingAs($this->admin)
            ->test('agentoom-core::admin.schedule-create-edit')
            ->set('timeframe', 'every_week')
            ->assertSet('minute', '0')
            ->assertSet('hour', '0')
            ->assertSet('day', '*')
            ->assertSet('month', '*')
            ->assertSet('day_of_week', '0');
    }

    public function test_it_prefills_every_month(): void
    {
        Livewire::actingAs($this->admin)
            ->test('agentoom-core::admin.schedule-create-edit')
            ->set('timeframe', 'every_month')
            ->assertSet('minute', '0')
            ->assertSet('hour', '0')
            ->assertSet('day', '1')
            ->assertSet('month', '*')
            ->assertSet('day_of_week', '*');
    }
}
