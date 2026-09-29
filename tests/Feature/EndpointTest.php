<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Endpoint;
use Agentoom\Core\Models\EndpointLog;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\PipelineRun;
use Agentoom\Core\Models\PipelineStep;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Ai;

class EndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_process_endpoint_request(): void
    {
        Ai::fakeAgent(DynamicAgent::class);

        $agent = Agent::factory()->create(['is_active' => true]);
        $endpoint = Endpoint::create([
            'agent_id' => $agent->id,
            'name' => 'Test Endpoint',
            'slug' => 'test-endpoint',
            'data_structure' => [
                ['name' => 'user_id', 'type' => 'integer'],
                ['name' => 'action', 'type' => 'string'],
            ],
            'is_active' => true,
        ]);

        $response = $this->postJson('/e/test-endpoint', [
            'user_id' => 123,
            'action' => 'login',
            'extra' => 'ignored',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('endpoint_logs', [
            'endpoint_id' => $endpoint->id,
            'status' => 'success',
        ]);

        $log = EndpointLog::first();
        $this->assertEquals(123, $log->request_data['user_id']);
        $this->assertEquals('login', $log->request_data['action']);
        $this->assertEquals('ignored', $log->request_data['extra']);
    }

    public function test_can_process_endpoint_request_targeting_pipeline(): void
    {
        Ai::fakeAgent(DynamicAgent::class);

        $agent = Agent::factory()->create(['is_active' => true]);
        $pipeline = Pipeline::create([
            'name' => 'Test Pipeline',
            'is_active' => true,
            'type' => Pipeline::TYPE_ENDPOINT,
        ]);

        // Step 1: Receive Data (pass-through)
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'order' => 0,
            'name' => 'Receive',
        ]);

        // Step 2: Agent
        PipelineStep::create([
            'pipeline_id' => $pipeline->id,
            'agent_id' => $agent->id,
            'order' => 1,
            'name' => 'Process',
        ]);

        $endpoint = Endpoint::create([
            'pipeline_id' => $pipeline->id,
            'name' => 'Pipeline Endpoint',
            'slug' => 'pipeline-endpoint',
            'is_active' => true,
        ]);

        $response = $this->postJson('/e/pipeline-endpoint', [
            'foo' => 'bar',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('endpoint_logs', [
            'endpoint_id' => $endpoint->id,
            'status' => 'success',
        ]);

        $this->assertDatabaseHas('pipeline_runs', [
            'pipeline_id' => $pipeline->id,
            'status' => 'completed',
        ]);

        $run = PipelineRun::first();
        $this->assertCount(2, $run->stepRuns);

        // The input to the pipeline is a JSON string of the processed data
        $expectedInput = json_encode(['foo' => 'bar'], JSON_PRETTY_PRINT);
        $this->assertEquals($expectedInput, $run->stepRuns[0]->output);
    }

    public function test_inactive_endpoint_returns_404(): void
    {
        $agent = Agent::factory()->create(['is_active' => true]);
        $endpoint = Endpoint::create([
            'agent_id' => $agent->id,
            'name' => 'Inactive Endpoint',
            'slug' => 'inactive-endpoint',
            'is_active' => false,
        ]);

        $response = $this->postJson('/e/inactive-endpoint', ['foo' => 'bar']);

        $response->assertStatus(404);
    }

    public function test_endpoint_with_allowed_sources_restricts_access(): void
    {
        Ai::fakeAgent(DynamicAgent::class);

        $agent = Agent::factory()->create(['is_active' => true]);
        $endpoint = Endpoint::create([
            'agent_id' => $agent->id,
            'name' => 'Restricted Endpoint',
            'slug' => 'restricted-endpoint',
            'allowed_sources' => ['1.2.3.4', 'example.com'],
            'is_active' => true,
        ]);

        // Request from unauthorized IP
        $response = $this->withServerVariables(['REMOTE_ADDR' => '9.9.9.9'])
            ->postJson('/e/restricted-endpoint', ['foo' => 'bar']);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Forbidden source']);

        // Request from authorized IP
        $response = $this->withServerVariables(['REMOTE_ADDR' => '1.2.3.4'])
            ->postJson('/e/restricted-endpoint', ['foo' => 'bar']);

        $response->assertStatus(200);

        // Request from authorized Referer domain
        $response = $this->withHeaders(['Referer' => 'http://example.com/some-page'])
            ->withServerVariables(['REMOTE_ADDR' => '9.9.9.9'])
            ->postJson('/e/restricted-endpoint', ['foo' => 'bar']);

        $response->assertStatus(200);

        // Request from authorized Origin domain
        $response = $this->withHeaders(['Origin' => 'https://example.com'])
            ->withServerVariables(['REMOTE_ADDR' => '9.9.9.9'])
            ->postJson('/e/restricted-endpoint', ['foo' => 'bar']);

        $response->assertStatus(200);
    }

    public function test_endpoint_with_auth_token_requires_valid_token(): void
    {
        Ai::fakeAgent(DynamicAgent::class);

        $agent = Agent::factory()->create(['is_active' => true]);
        $endpoint = Endpoint::create([
            'agent_id' => $agent->id,
            'name' => 'Token Protected',
            'slug' => 'token-protected',
            'auth_token_enabled' => true,
            'auth_token' => 'my-secret-token-32chars!!',
            'auth_token_header' => 'X-Agentoom-Signature',
            'is_active' => true,
        ]);

        // Request without the token header
        $response = $this->postJson('/e/token-protected', ['foo' => 'bar']);
        $response->assertStatus(403);
        $response->assertJson(['error' => 'Forbidden source']);

        // Request with wrong token value
        $response = $this->withHeaders(['X-Agentoom-Signature' => 'wrong-token'])
            ->postJson('/e/token-protected', ['foo' => 'bar']);
        $response->assertStatus(403);
        $response->assertJson(['error' => 'Forbidden source']);

        // Request with correct token
        $response = $this->withHeaders(['X-Agentoom-Signature' => 'my-secret-token-32chars!!'])
            ->postJson('/e/token-protected', ['foo' => 'bar']);
        $response->assertStatus(200);
    }

    public function test_endpoint_with_auth_token_uses_custom_header(): void
    {
        Ai::fakeAgent(DynamicAgent::class);

        $agent = Agent::factory()->create(['is_active' => true]);
        $endpoint = Endpoint::create([
            'agent_id' => $agent->id,
            'name' => 'Custom Header Token',
            'slug' => 'custom-header-token',
            'auth_token_enabled' => true,
            'auth_token' => 'custom-header-token-value',
            'auth_token_header' => 'X-Custom-Auth',
            'is_active' => true,
        ]);

        $endpoint->refresh();
        $this->assertTrue($endpoint->auth_token_enabled);
        $this->assertEquals('X-Custom-Auth', $endpoint->auth_token_header);

        // Request with token in custom header
        $response = $this->withHeaders(['X-Custom-Auth' => 'custom-header-token-value'])
            ->postJson('/e/custom-header-token', ['foo' => 'bar']);
        $response->assertStatus(200);

        // Request with token in default header (should fail - no custom header set)
        $response = $this->flushHeaders()
            ->withHeaders(['X-Agentoom-Signature' => 'custom-header-token-value'])
            ->postJson('/e/custom-header-token', ['foo' => 'bar']);
        $response->assertStatus(403);
    }

    public function test_endpoint_with_disabled_auth_token_ignores_token(): void
    {
        Ai::fakeAgent(DynamicAgent::class);

        $agent = Agent::factory()->create(['is_active' => true]);
        $endpoint = Endpoint::create([
            'agent_id' => $agent->id,
            'name' => 'Token Disabled',
            'slug' => 'token-disabled',
            'auth_token_enabled' => false,
            'auth_token' => 'some-token',
            'auth_token_header' => 'X-Agentoom-Signature',
            'is_active' => true,
        ]);

        // Request without any token should pass since token auth is disabled
        $response = $this->postJson('/e/token-disabled', ['foo' => 'bar']);
        $response->assertStatus(200);
    }

    public function test_endpoint_token_check_happens_after_allowed_sources(): void
    {
        Ai::fakeAgent(DynamicAgent::class);

        $agent = Agent::factory()->create(['is_active' => true]);
        $endpoint = Endpoint::create([
            'agent_id' => $agent->id,
            'name' => 'Both Restrictions',
            'slug' => 'both-restrictions',
            'allowed_sources' => ['1.2.3.4'],
            'auth_token_enabled' => true,
            'auth_token' => 'dual-auth-token',
            'auth_token_header' => 'X-Agentoom-Signature',
            'is_active' => true,
        ]);

        // Wrong IP, correct token
        $response = $this->withServerVariables(['REMOTE_ADDR' => '9.9.9.9'])
            ->withHeaders(['X-Agentoom-Signature' => 'dual-auth-token'])
            ->postJson('/e/both-restrictions', ['foo' => 'bar']);
        $response->assertStatus(403);

        // Correct IP, wrong token
        $response = $this->withServerVariables(['REMOTE_ADDR' => '1.2.3.4'])
            ->withHeaders(['X-Agentoom-Signature' => 'wrong-token'])
            ->postJson('/e/both-restrictions', ['foo' => 'bar']);
        $response->assertStatus(403);

        // Both correct
        $response = $this->withServerVariables(['REMOTE_ADDR' => '1.2.3.4'])
            ->withHeaders(['X-Agentoom-Signature' => 'dual-auth-token'])
            ->postJson('/e/both-restrictions', ['foo' => 'bar']);
        $response->assertStatus(200);
    }
}
