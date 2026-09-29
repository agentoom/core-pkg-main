<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Schedule;
use Agentoom\Core\Services\TestActionableService;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class ScheduleCreateEditPrefillTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prefills_instructions_on_method_selection(): void
    {
        $user = User::factory()->create();
        Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $user->assignRole('superadmin');
        $this->actingAs($user);

        $key = TestActionableService::class.'@testMethod';

        Livewire::test('agentoom-core::admin.schedule-create-edit')
            ->set('target_type', 'custom')
            ->set('selectedMethodKey', $key)
            ->assertSet('model', TestActionableService::class)
            ->assertSet('method', 'testMethod')
            ->assertSet('instructions', function ($value) {
                return str_contains($value, 'param1') && str_contains($value, 'param2');
            });
    }

    public function test_it_initializes_correctly_when_editing_custom_method(): void
    {
        $user = User::factory()->create();
        Role::where('name', 'superadmin')->exists() ?: Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $user->assignRole('superadmin');
        $this->actingAs($user);

        $schedule = Schedule::factory()->create([
            'name' => 'Custom Schedule',
            'model' => TestActionableService::class,
            'method' => 'testMethod',
            'instructions' => '{"param1": "foo", "param2": 123}',
            'active' => true,
        ]);

        Livewire::test('agentoom-core::admin.schedule-create-edit', ['schedule' => $schedule])
            ->assertSet('target_type', 'custom')
            ->assertSet('selectedMethodKey', TestActionableService::class.'@testMethod')
            ->assertSet('instructions', '{"param1": "foo", "param2": 123}');
    }
}
