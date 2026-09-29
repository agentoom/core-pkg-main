<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class AgentToolsRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_tools_route_is_defined()
    {
        $this->assertTrue(\Route::has('admin.agent-tools.index'));
    }

    public function test_authenticated_user_can_access_agent_tools_index()
    {
        Role::create(['name' => 'admin']);
        $user = User::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)->get(route('admin.agent-tools.index'));

        $response->assertStatus(200);
    }
}
