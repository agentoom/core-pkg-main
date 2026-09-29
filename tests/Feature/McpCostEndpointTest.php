<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class McpCostEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function payload(string $key = 'request-1'): array
    {
        return ['service' => 'dataforseo', 'cost' => 0.05, 'currency' => 'USD', 'idempotency_key' => $key];
    }

    private function token(): array
    {
        return ['Authorization' => 'Bearer valid-token-123'];
    }

    public function test_it_rejects_requests_without_token(): void
    {
        $response = $this->postJson('/api/mcp/costs', $this->payload());
        $response->assertStatus(401)->assertJson(['message' => 'MCP API token not configured.']);
    }

    public function test_it_rejects_requests_with_invalid_token(): void
    {
        Configuration::setValue('mcp_api_token', 'valid-token-123');
        $response = $this->postJson('/api/mcp/costs', $this->payload(), ['Authorization' => 'Bearer wrong-token']);
        $response->assertStatus(401)->assertJson(['message' => 'Unauthorized.']);
    }

    public function test_it_accepts_usd_and_deducts_once_for_an_idempotent_request(): void
    {
        Configuration::setValue('mcp_api_token', 'valid-token-123');
        Configuration::setValue('app_ai_credits', 100.0);

        $first = $this->postJson('/api/mcp/costs', $this->payload(), $this->token());
        $retry = $this->postJson('/api/mcp/costs', $this->payload(), $this->token());

        $first->assertStatus(201)->assertJson(['message' => 'Cost logged successfully.']);
        $retry->assertStatus(200)->assertJson(['message' => 'Cost already logged.']);
        $this->assertEquals(99.95, (float) Configuration::getValue('app_ai_credits'));
        $this->assertDatabaseHas('ai_usage_logs', ['action_type' => 'mcp_dataforseo', 'cost' => 0.05, 'pricing_source' => 'mcp_reported', 'idempotency_key' => 'request-1']);
        $this->assertDatabaseCount('ai_usage_logs', 1);
    }

    public function test_it_rejects_non_usd_currency(): void
    {
        Configuration::setValue('mcp_api_token', 'valid-token-123');
        $response = $this->postJson('/api/mcp/costs', array_merge($this->payload(), ['currency' => 'EUR']), $this->token());
        $response->assertStatus(422)->assertJsonValidationErrors(['currency']);
    }

    public function test_it_validates_required_fields(): void
    {
        Configuration::setValue('mcp_api_token', 'valid-token-123');
        $response = $this->postJson('/api/mcp/costs', [], $this->token());
        $response->assertStatus(422)->assertJsonValidationErrors(['service', 'cost', 'currency', 'idempotency_key']);
    }

    public function test_it_validates_cost_is_numeric_and_non_negative(): void
    {
        Configuration::setValue('mcp_api_token', 'valid-token-123');
        $invalid = $this->postJson('/api/mcp/costs', array_merge($this->payload(), ['cost' => 'not-a-number']), $this->token());
        $negative = $this->postJson('/api/mcp/costs', array_merge($this->payload('request-2'), ['cost' => -1]), $this->token());
        $invalid->assertStatus(422)->assertJsonValidationErrors(['cost']);
        $negative->assertStatus(422)->assertJsonValidationErrors(['cost']);
    }
}
