<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Services\TestActionableService;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class PipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prefills_step_instructions_on_method_selection(): void
    {
        $user = User::factory()->create();
        Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $user->assignRole('superadmin');
        $this->actingAs($user);

        $key = TestActionableService::class.'@testMethod';

        Livewire::test('agentoom-core::admin.pipeline-create-edit')
            ->call('addStep')
            ->set('steps.0.type', 'custom')
            ->set('steps.0.method_key', $key)
            ->assertSet('steps.0.class', TestActionableService::class)
            ->assertSet('steps.0.method', 'testMethod')
            ->assertSet('steps.0.instructions_override', function ($value) {
                return str_contains($value, 'param1') && str_contains($value, 'param2');
            });
    }

    public function test_it_saves_pipeline_with_custom_method_step(): void
    {
        $user = User::factory()->create();
        Role::where('name', 'superadmin')->exists() ?: Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $user->assignRole('superadmin');
        $this->actingAs($user);

        $key = TestActionableService::class.'@testMethod';

        Livewire::test('agentoom-core::admin.pipeline-create-edit')
            ->set('name', 'Test Pipeline')
            ->call('addStep')
            ->set('steps.0.type', 'custom')
            ->set('steps.0.method_key', $key)
            ->call('save')
            ->assertHasNoErrors();

        $pipeline = Pipeline::where('name', 'Test Pipeline')->first();
        $this->assertNotNull($pipeline);
        $this->assertCount(1, $pipeline->steps);
        $this->assertEquals(TestActionableService::class, $pipeline->steps->first()->class);
        $this->assertEquals('testMethod', $pipeline->steps->first()->method);
    }

    public function test_it_automatically_sets_type_to_custom_on_method_selection(): void
    {
        $user = User::factory()->create();
        Role::where('name', 'superadmin')->exists() ?: Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $user->assignRole('superadmin');
        $this->actingAs($user);

        $key = TestActionableService::class.'@testMethod';

        Livewire::test('agentoom-core::admin.pipeline-create-edit')
            ->set('name', 'Auto Type Pipeline')
            ->call('addStep')
            ->assertSet('steps.0.type', 'agent')
            ->set('steps.0.method_key', $key)
            ->assertSet('steps.0.type', 'custom')
            ->call('save')
            ->assertHasNoErrors();

        $pipeline = Pipeline::where('name', 'Auto Type Pipeline')->first();
        $this->assertNotNull($pipeline);
        $this->assertEquals(TestActionableService::class, $pipeline->steps->first()->class);
        $this->assertEquals('testMethod', $pipeline->steps->first()->method);
    }

    public function test_it_saves_pipeline_with_receive_data_step(): void
    {
        $user = User::factory()->create();
        Role::where('name', 'superadmin')->exists() ?: Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $user->assignRole('superadmin');
        $this->actingAs($user);

        Livewire::test('agentoom-core::admin.pipeline-create-edit')
            ->set('name', 'Receive Data Pipeline')
            ->set('type', 'endpoint')
            ->call('addStep')
            ->assertSet('steps.0.type', 'receive_data')
            ->call('save')
            ->assertHasNoErrors();

        $pipeline = Pipeline::where('name', 'Receive Data Pipeline')->first();
        $this->assertNotNull($pipeline);
        $this->assertEquals('endpoint', $pipeline->type);
        $this->assertCount(1, $pipeline->steps);
        $this->assertNull($pipeline->steps->first()->agent_id);
        $this->assertNull($pipeline->steps->first()->class);
        $this->assertNull($pipeline->steps->first()->method);
    }

    public function test_it_prevents_receive_data_as_first_step_in_standard_pipeline(): void
    {
        $user = User::factory()->create();
        Role::where('name', 'superadmin')->exists() ?: Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $user->assignRole('superadmin');
        $this->actingAs($user);

        Livewire::test('agentoom-core::admin.pipeline-create-edit')
            ->set('name', 'Invalid Standard Pipeline')
            ->set('type', 'standard')
            ->call('addStep')
            ->set('steps.0.type', 'receive_data')
            ->call('save')
            ->assertHasErrors(['steps.0.type']);
    }

    public function test_it_prevents_endpoint_pipeline_without_receive_data_first_step(): void
    {
        $user = User::factory()->create();
        Role::where('name', 'superadmin')->exists() ?: Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $user->assignRole('superadmin');
        $this->actingAs($user);

        Livewire::test('agentoom-core::admin.pipeline-create-edit')
            ->set('name', 'Invalid Endpoint Pipeline')
            ->set('type', 'endpoint')
            ->call('addStep')
            ->set('steps.0.type', 'agent')
            ->call('save')
            ->assertHasErrors(['steps']);
    }
}
