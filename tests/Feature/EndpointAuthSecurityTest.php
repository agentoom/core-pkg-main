<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Endpoint;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\User;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Ai;

class EndpointAuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_accessing_endpoint_logs_user_into_session_reproduction(): void
    {
        Ai::fakeAgent(DynamicAgent::class);

        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $systemUser = $project->systemUser;

        $agent = Agent::factory()->create([
            'project_id' => $project->id,
            'is_active' => true,
        ]);

        $endpoint = Endpoint::create([
            'project_id' => $project->id,
            'agent_id' => $agent->id,
            'name' => 'Security Test Endpoint',
            'slug' => 'security-test',
            'is_active' => true,
        ]);

        // Initially not logged in
        $this->assertFalse(Auth::check());

        // Call the endpoint. Auth::onceUsingId() will set the user for THIS request.
        $response = $this->get('/e/security-test');
        $response->assertStatus(200);

        // To verify it didn't persist to the session, we check if the session is empty/doesn't have the user
        // and we make another request.
        $this->assertFalse(session()->has(Auth::getName()), 'The session should not contain the user ID');

        // Reset the Auth singleton for the next request simulation
        Auth::forgetUser();

        // Second request to a dummy route (or just another check)
        $this->get('/');
        $this->assertFalse(Auth::check(), 'User should NOT be logged in on subsequent requests');
    }
}
