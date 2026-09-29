<?php

namespace Agentoom\Core\Tests\Feature\Admin;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Endpoint;
use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class EndpointPromptLivewireTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_saves_prompt(): void
    {
        $user = User::factory()->create();
        Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $user->assignRole('superadmin');
        $this->actingAs($user);

        $agent = Agent::factory()->create(['is_active' => true]);

        Livewire::test('agentoom-core::admin.endpoint-create-edit')
            ->set('name', 'New Endpoint')
            ->set('agent_id', $agent->id)
            ->set('prompt', 'Test prompt content')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('endpoints', [
            'name' => 'New Endpoint',
            'prompt' => 'Test prompt content',
            'agent_id' => $agent->id,
        ]);
    }

    public function test_it_loads_existing_prompt(): void
    {
        $user = User::factory()->create();
        Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        $user->assignRole('superadmin');
        $this->actingAs($user);

        $agent = Agent::factory()->create(['is_active' => true]);
        $endpoint = Endpoint::create([
            'name' => 'Existing Endpoint',
            'slug' => 'existing-endpoint',
            'agent_id' => $agent->id,
            'prompt' => 'Loaded prompt content',
            'is_active' => true,
        ]);

        Livewire::test('agentoom-core::admin.endpoint-create-edit', ['endpoint' => $endpoint])
            ->assertSet('prompt', 'Loaded prompt content');
    }
}
