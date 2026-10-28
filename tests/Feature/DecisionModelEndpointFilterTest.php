<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Endpoint;
use Agentoom\Core\Models\EndpointLog;
use Agentoom\Core\Models\Provider;
use Agentoom\Core\Models\ProviderModel;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Ai;

class DecisionModelEndpointFilterTest extends TestCase
{
    use RefreshDatabase;

    protected Provider $provider;
    protected ProviderModel $model;
    protected Agent $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = Provider::create([
            'name' => 'openrouter',
            'api_key' => 'sk-test-key',
            'url' => 'https://openrouter.ai/api/v1',
        ]);

        $this->model = ProviderModel::create([
            'provider_id' => $this->provider->id,
            'model_name' => 'typesafe/jev-1.14',
            'model_features' => [
                'architecture' => ['output_modalities' => ['decisions']],
            ],
            'is_active' => true,
        ]);

        $this->agent = Agent::factory()->create(['is_active' => true]);
    }

    public function test_endpoint_request_passes_when_decision_filter_confidence_meets_threshold(): void
    {
        Ai::fakeAgent(DynamicAgent::class);

        $endpoint = Endpoint::create([
            'agent_id' => $this->agent->id,
            'name' => 'Order Webhook',
            'slug' => 'order-webhook',
            'is_active' => true,
            'decision_filter_enabled' => true,
            'decision_filter_provider' => 'openrouter',
            'decision_filter_model' => 'typesafe/jev-1.14',
            'decision_filter_instructions' => 'Is this a valid order event with an amount?',
            'decision_filter_threshold' => 0.80,
            'decision_filter_reject_action' => 'skip_200',
        ]);

        Http::fake([
            '*/api/alpha/decisions' => Http::response([
                'answers' => [
                    'decision' => [
                        'type' => 'noul',
                        'noul' => 0.94,
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/e/order-webhook', [
            'event' => 'order.created',
            'amount' => 49.99,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('endpoint_logs', [
            'endpoint_id' => $endpoint->id,
            'status' => 'success',
        ]);
    }

    public function test_endpoint_request_is_filtered_and_skipped_when_confidence_below_threshold(): void
    {
        $endpoint = Endpoint::create([
            'agent_id' => $this->agent->id,
            'name' => 'Order Webhook',
            'slug' => 'order-webhook-skip',
            'is_active' => true,
            'decision_filter_enabled' => true,
            'decision_filter_provider' => 'openrouter',
            'decision_filter_model' => 'typesafe/jev-1.14',
            'decision_filter_instructions' => 'Is this a valid order event with an amount?',
            'decision_filter_threshold' => 0.80,
            'decision_filter_reject_action' => 'skip_200',
        ]);

        Http::fake([
            '*/api/alpha/decisions' => Http::response([
                'answers' => [
                    'decision' => [
                        'type' => 'noul',
                        'noul' => 0.35,
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/e/order-webhook-skip', [
            'event' => 'ping',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'filtered',
            'confidence' => 0.35,
        ]);

        $log = EndpointLog::where('endpoint_id', $endpoint->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals('filtered', $log->status);
        $this->assertStringContainsString('Filtered out by decision model', $log->error_message);
    }

    public function test_endpoint_request_is_rejected_with_422_when_configured(): void
    {
        $endpoint = Endpoint::create([
            'agent_id' => $this->agent->id,
            'name' => 'Order Webhook 422',
            'slug' => 'order-webhook-422',
            'is_active' => true,
            'decision_filter_enabled' => true,
            'decision_filter_provider' => 'openrouter',
            'decision_filter_model' => 'typesafe/jev-1.14',
            'decision_filter_instructions' => 'Is this a valid order event?',
            'decision_filter_threshold' => 0.80,
            'decision_filter_reject_action' => 'reject_422',
        ]);

        Http::fake([
            '*/api/alpha/decisions' => Http::response([
                'answers' => [
                    'decision' => [
                        'type' => 'noul',
                        'noul' => 0.40,
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/e/order-webhook-422', [
            'junk' => 'data',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'status' => 'rejected',
            'confidence' => 0.40,
        ]);

        $log = EndpointLog::where('endpoint_id', $endpoint->id)->first();
        $this->assertEquals('filtered', $log->status);
    }

    public function test_endpoint_request_is_silently_dropped_when_configured(): void
    {
        $endpoint = Endpoint::create([
            'agent_id' => $this->agent->id,
            'name' => 'Order Webhook Silent',
            'slug' => 'order-webhook-silent',
            'is_active' => true,
            'decision_filter_enabled' => true,
            'decision_filter_provider' => 'openrouter',
            'decision_filter_model' => 'typesafe/jev-1.14',
            'decision_filter_instructions' => 'Is this a valid order event?',
            'decision_filter_threshold' => 0.80,
            'decision_filter_reject_action' => 'silent_drop',
        ]);

        Http::fake([
            '*/api/alpha/decisions' => Http::response([
                'answers' => [
                    'decision' => [
                        'type' => 'noul',
                        'noul' => 0.20,
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/e/order-webhook-silent', [
            'spam' => true,
        ]);

        $response->assertStatus(204);

        $log = EndpointLog::where('endpoint_id', $endpoint->id)->first();
        $this->assertEquals('filtered', $log->status);
    }

    public function test_endpoint_request_bypasses_filter_when_filter_is_disabled(): void
    {
        Ai::fakeAgent(DynamicAgent::class);

        $endpoint = Endpoint::create([
            'agent_id' => $this->agent->id,
            'name' => 'Order Webhook Disabled',
            'slug' => 'order-webhook-disabled',
            'is_active' => true,
            'decision_filter_enabled' => false,
            'decision_filter_instructions' => 'Evaluate condition',
        ]);

        $response = $this->postJson('/e/order-webhook-disabled', [
            'payload' => 'hello',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('endpoint_logs', [
            'endpoint_id' => $endpoint->id,
            'status' => 'success',
        ]);
    }
}
