<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\Schedule;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class ScheduleTargetTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $user->assignRole('superadmin');
        $this->actingAs($user);
    }

    public function test_it_identifies_agent_target_type_correctly_even_with_execute_method(): void
    {
        $agent = Agent::factory()->create();
        $schedule = Schedule::factory()->create([
            'model' => Agent::class,
            'model_id' => $agent->id,
            'method' => 'execute',
        ]);

        Livewire::test('agentoom-core::admin.schedule-create-edit', ['schedule' => $schedule])
            ->assertSet('target_type', 'agent')
            ->assertSet('method', null);
    }

    public function test_it_identifies_agent_target_type_correctly_with_null_method(): void
    {
        $agent = Agent::factory()->create();
        $schedule = Schedule::factory()->create([
            'model' => Agent::class,
            'model_id' => $agent->id,
            'method' => null,
        ]);

        Livewire::test('agentoom-core::admin.schedule-create-edit', ['schedule' => $schedule])
            ->assertSet('target_type', 'agent');
    }

    public function test_it_identifies_pipeline_target_type_correctly(): void
    {
        $pipeline = Pipeline::factory()->create();
        $schedule = Schedule::factory()->create([
            'model' => Pipeline::class,
            'model_id' => $pipeline->id,
            'method' => null,
        ]);

        Livewire::test('agentoom-core::admin.schedule-create-edit', ['schedule' => $schedule])
            ->assertSet('target_type', 'pipeline');
    }

    public function test_it_identifies_custom_target_type_correctly(): void
    {
        $schedule = Schedule::factory()->create([
            'model' => 'Some\\Custom\\Class',
            'model_id' => null,
            'method' => 'someMethod',
        ]);

        Livewire::test('agentoom-core::admin.schedule-create-edit', ['schedule' => $schedule])
            ->assertSet('target_type', 'custom');
    }
}
