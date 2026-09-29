<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AgentSkill;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\Endpoint;
use Agentoom\Core\Models\McpActionable;
use Agentoom\Core\Models\McpServer;
use Agentoom\Core\Models\Pipeline;
use Agentoom\Core\Models\Schedule;
use Agentoom\Core\Models\WebhookSubscription;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class McpResourcesEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'valid-token-123';

    private const MCP_URL = 'https://example-mcp-server.com';

    private function validHeaders(): array
    {
        return ['Authorization' => 'Bearer '.self::TOKEN];
    }

    private function createMcpServer(): McpServer
    {
        return McpServer::create([
            'name' => 'Test MCP Server',
            'url' => self::MCP_URL,
            'transport_type' => 'http',
            'status' => 'active',
        ]);
    }

    public function test_it_rejects_requests_without_token(): void
    {
        $response = $this->postJson('/api/mcp/resources', [
            'mcp_url' => self::MCP_URL,
            'type' => 'pipeline',
            'slug' => 'my-server-my-pipeline',
            'name' => 'My Pipeline',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['message' => 'MCP API token not configured.']);
    }

    public function test_it_rejects_requests_with_invalid_token(): void
    {
        Configuration::setValue('mcp_api_token', self::TOKEN);

        $response = $this->postJson('/api/mcp/resources', [
            'mcp_url' => self::MCP_URL,
            'type' => 'pipeline',
            'slug' => 'my-server-my-pipeline',
            'name' => 'My Pipeline',
        ], [
            'Authorization' => 'Bearer wrong-token',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Unauthorized.']);
    }

    public function test_it_returns_404_when_mcp_server_not_found(): void
    {
        Configuration::setValue('mcp_api_token', self::TOKEN);

        $response = $this->postJson('/api/mcp/resources', [
            'mcp_url' => 'https://unknown-server.com',
            'type' => 'pipeline',
            'slug' => 'unknown-pipeline',
            'name' => 'Unknown Pipeline',
        ], $this->validHeaders());

        $response->assertStatus(404);
        $response->assertJson(['message' => 'MCP server not found.']);
    }

    public function test_it_validates_required_fields(): void
    {
        Configuration::setValue('mcp_api_token', self::TOKEN);

        $response = $this->postJson('/api/mcp/resources', [], $this->validHeaders());

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['mcp_url', 'type', 'slug', 'name']);
    }

    public function test_it_validates_invalid_type(): void
    {
        Configuration::setValue('mcp_api_token', self::TOKEN);
        $this->createMcpServer();

        $response = $this->postJson('/api/mcp/resources', [
            'mcp_url' => self::MCP_URL,
            'type' => 'invalid_type',
            'slug' => 'my-server-test',
            'name' => 'Test Resource',
        ], $this->validHeaders());

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['type']);
    }

    public function test_it_creates_a_pipeline_successfully(): void
    {
        Configuration::setValue('mcp_api_token', self::TOKEN);
        $mcpServer = $this->createMcpServer();

        $response = $this->postJson('/api/mcp/resources', [
            'mcp_url' => self::MCP_URL,
            'type' => 'pipeline',
            'slug' => 'my-mcp-server-my-pipeline',
            'name' => 'My Pipeline',
            'description' => 'A test pipeline',
            'is_active' => true,
        ], $this->validHeaders());

        $response->assertStatus(201);
        $response->assertJson([
            'message' => 'Pipeline created successfully.',
            'action' => 'created',
            'name' => 'My Pipeline',
            'slug' => 'my-mcp-server-my-pipeline',
            'mcp_name' => 'Test MCP Server',
        ]);
        $this->assertTrue($response->json('id') > 0);

        $this->assertDatabaseHas('pipelines', [
            'name' => 'My Pipeline',
            'slug' => 'my-mcp-server-my-pipeline',
            'mcp_server_id' => $mcpServer->id,
            'project_id' => $mcpServer->project_id,
            'is_active' => true,
        ]);
    }

    public function test_it_creates_a_pipeline_with_steps_and_variables(): void
    {
        Configuration::setValue('mcp_api_token', self::TOKEN);
        $mcpServer = $this->createMcpServer();

        $agent = Agent::factory()->create(['is_active' => true]);

        $response = $this->postJson('/api/mcp/resources', [
            'mcp_url' => self::MCP_URL,
            'type' => 'pipeline',
            'slug' => 'my-mcp-server-my-pipeline-with-steps',
            'name' => 'Pipeline With Steps',
            'description' => 'A pipeline with step configuration',
            'is_active' => true,
            'variables' => [
                ['key' => 'company_name', 'value' => 'Acme Corp'],
            ],
            'steps' => [
                [
                    'type' => 'agent',
                    'agent_id' => $agent->id,
                    'order' => 0,
                    'name' => 'Enrich Lead',
                    'instructions_override' => 'Enrich the lead data.',
                    'force_json' => true,
                    'pass_output_as_input' => false,
                    'pass_all_previous_outputs' => false,
                    'on_error' => 'retry',
                    'retry_attempts' => 3,
                    'retry_delay' => 5,
                    'is_active' => true,
                ],
                [
                    'type' => 'agent',
                    'agent_id' => $agent->id,
                    'order' => 1,
                    'name' => 'Send Email',
                    'instructions_override' => 'Compose email for {1.company}. The company is {{company_name}}.',
                    'selected_outputs' => [0],
                    'pass_all_previous_outputs' => false,
                    'on_error' => 'continue',
                    'is_active' => true,
                ],
                [
                    'type' => 'custom',
                    'order' => 2,
                    'name' => 'Log Result',
                    'class' => 'App\\Services\\LogService',
                    'method' => 'log',
                    'instructions_override' => '[{"name":"message","type":"string","value":"Pipeline completed for {1.company}"}]',
                    'on_error' => 'fail',
                    'is_active' => true,
                ],
            ],
            'failure_step' => [
                [
                    'type' => 'agent',
                    'agent_id' => $agent->id,
                    'order' => 0,
                    'name' => 'Failure Notification',
                    'instructions_override' => 'Notify team about pipeline failure.',
                    'on_error' => 'fail',
                ],
            ],
        ], $this->validHeaders());

        $response->assertStatus(201);
        $response->assertJson([
            'message' => 'Pipeline created successfully.',
            'action' => 'created',
            'name' => 'Pipeline With Steps',
            'slug' => 'my-mcp-server-my-pipeline-with-steps',
            'mcp_name' => 'Test MCP Server',
        ]);
        $this->assertTrue($response->json('id') > 0);

        $this->assertDatabaseHas('pipelines', [
            'slug' => 'my-mcp-server-my-pipeline-with-steps',
            'mcp_server_id' => $mcpServer->id,
        ]);

        $pipeline = Pipeline::where('slug', 'my-mcp-server-my-pipeline-with-steps')->first();
        $this->assertNotNull($pipeline);
        $this->assertNotNull($pipeline->config);
        $this->assertArrayHasKey('variables', $pipeline->config);
        $this->assertEquals('Acme Corp', $pipeline->config['variables'][0]['value']);

        // Assert 3 regular steps + 1 failure step = 4 total
        $this->assertEquals(4, $pipeline->steps()->count());

        $regularSteps = $pipeline->steps()->where('is_failure_handler', false)->orderBy('order')->get();

        // Step 0: agent step
        $this->assertEquals(0, $regularSteps[0]->order);
        $this->assertEquals('Enrich Lead', $regularSteps[0]->name);
        $this->assertEquals($agent->id, $regularSteps[0]->agent_id);
        $this->assertTrue((bool) $regularSteps[0]->force_json);
        $this->assertEquals('retry', $regularSteps[0]->on_error);
        $this->assertEquals(3, $regularSteps[0]->retry_attempts);

        // Step 1: agent step with selected_outputs
        $this->assertEquals(1, $regularSteps[1]->order);
        $this->assertEquals([0], $regularSteps[1]->selected_outputs);

        // Step 2: custom action step
        $this->assertEquals(2, $regularSteps[2]->order);
        $this->assertEquals('App\\Services\\LogService', $regularSteps[2]->class);
        $this->assertEquals('log', $regularSteps[2]->method);

        // Failure handler
        $failureStep = $pipeline->failureStep()->first();
        $this->assertNotNull($failureStep);
        $this->assertTrue((bool) $failureStep->is_failure_handler);
    }

    public function test_it_creates_a_pipeline_with_default_step_order(): void
    {
        Configuration::setValue('mcp_api_token', self::TOKEN);
        $this->createMcpServer();

        $agent = Agent::factory()->create(['is_active' => true]);

        $response = $this->postJson('/api/mcp/resources', [
            'mcp_url' => self::MCP_URL,
            'type' => 'pipeline',
            'slug' => 'auto-order-pipeline',
            'name' => 'Auto Order Pipeline',
            'steps' => [
                ['type' => 'agent', 'agent_id' => $agent->id, 'instructions_override' => 'Step A'],
                ['type' => 'agent', 'agent_id' => $agent->id, 'instructions_override' => 'Step B'],
                ['type' => 'agent', 'agent_id' => $agent->id, 'instructions_override' => 'Step C'],
            ],
        ], $this->validHeaders());

        $response->assertStatus(201);

        $pipeline = Pipeline::where('slug', 'auto-order-pipeline')->first();
        $steps = $pipeline->steps()->orderBy('order')->get();

        $this->assertEquals(0, $steps[0]->order);
        $this->assertEquals(1, $steps[1]->order);
        $this->assertEquals(2, $steps[2]->order);
    }

    public function test_it_creates_an_endpoint_successfully(): void
    {
        Configuration::setValue('mcp_api_token', self::TOKEN);
        $mcpServer = $this->createMcpServer();

        $response = $this->postJson('/api/mcp/resources', [
            'mcp_url' => self::MCP_URL,
            'type' => 'endpoint',
            'slug' => 'my-mcp-server-my-endpoint',
            'name' => 'My Endpoint',
            'description' => 'A test endpoint',
            'is_active' => true,
            'data_structure' => ['fields' => ['email', 'name']],
        ], $this->validHeaders());

        $response->assertStatus(201);
        $response->assertJson([
            'message' => 'Endpoint created successfully.',
            'action' => 'created',
            'name' => 'My Endpoint',
            'slug' => 'my-mcp-server-my-endpoint',
            'mcp_name' => 'Test MCP Server',
        ]);
        $this->assertTrue($response->json('id') > 0);

        $this->assertDatabaseHas('endpoints', [
            'name' => 'My Endpoint',
            'slug' => 'my-mcp-server-my-endpoint',
            'mcp_server_id' => $mcpServer->id,
            'project_id' => $mcpServer->project_id,
            'is_active' => true,
        ]);
    }

    public function test_it_creates_a_webhook_successfully(): void
    {
        Configuration::setValue('mcp_api_token', self::TOKEN);
        $mcpServer = $this->createMcpServer();

        $response = $this->postJson('/api/mcp/resources', [
            'mcp_url' => self::MCP_URL,
            'type' => 'webhook',
            'slug' => 'my-mcp-server-my-webhook',
            'name' => 'My Webhook',
            'url' => 'https://example.com/webhook',
            'events' => ['agent.completed', 'pipeline.failed'],
            'is_active' => true,
        ], $this->validHeaders());

        $response->assertStatus(201);
        $response->assertJson([
            'message' => 'Webhook created successfully.',
            'action' => 'created',
            'name' => 'My Webhook',
            'slug' => 'my-mcp-server-my-webhook',
            'mcp_name' => 'Test MCP Server',
        ]);
        $this->assertTrue($response->json('id') > 0);

        $this->assertDatabaseHas('webhook_subscriptions', [
            'slug' => 'my-mcp-server-my-webhook',
            'mcp_server_id' => $mcpServer->id,
            'project_id' => $mcpServer->project_id,
            'is_active' => true,
        ]);
    }

    public function test_it_creates_a_schedule_successfully(): void
    {
        Configuration::setValue('mcp_api_token', self::TOKEN);
        $mcpServer = $this->createMcpServer();

        $response = $this->postJson('/api/mcp/resources', [
            'mcp_url' => self::MCP_URL,
            'type' => 'schedule',
            'slug' => 'my-mcp-server-my-schedule',
            'name' => 'My Schedule',
            'minute' => '0',
            'hour' => '*/6',
            'day' => '*',
            'month' => '*',
            'day_of_week' => '*',
            'timezone' => 'UTC',
            'schedule_model' => 'Agentoom\\Core\\Models\\Agent',
            'model_id' => 1,
            'is_active' => true,
        ], $this->validHeaders());

        $response->assertStatus(201);
        $response->assertJson([
            'message' => 'Schedule created successfully.',
            'action' => 'created',
            'name' => 'My Schedule',
            'slug' => 'my-mcp-server-my-schedule',
            'mcp_name' => 'Test MCP Server',
        ]);
        $this->assertTrue($response->json('id') > 0);

        $this->assertDatabaseHas('schedules', [
            'name' => 'My Schedule',
            'slug' => 'my-mcp-server-my-schedule',
            'mcp_server_id' => $mcpServer->id,
            'project_id' => $mcpServer->project_id,
            'active' => true,
        ]);
    }

    public function test_it_creates_an_agent_successfully(): void
    {
        Configuration::setValue('mcp_api_token', self::TOKEN);
        $mcpServer = $this->createMcpServer();

        $response = $this->postJson('/api/mcp/resources', [
            'mcp_url' => self::MCP_URL,
            'type' => 'agent',
            'slug' => 'my-mcp-server-my-agent',
            'name' => 'My Agent',
            'description' => 'A test agent',
            'instructions' => 'You are a helpful assistant.',
            'role' => 'Support agent',
            'objective' => 'Help users with their queries',
            'is_active' => true,
            'is_public' => true,
            'config' => ['model' => 'gpt-4'],
        ], $this->validHeaders());

        $response->assertStatus(201);
        $response->assertJson([
            'message' => 'Agent created successfully.',
            'action' => 'created',
            'name' => 'My Agent',
            'slug' => 'my-mcp-server-my-agent',
            'mcp_name' => 'Test MCP Server',
        ]);
        $this->assertTrue($response->json('id') > 0);

        $this->assertDatabaseHas('agents', [
            'name' => 'My Agent',
            'slug' => 'my-mcp-server-my-agent',
            'mcp_server_id' => $mcpServer->id,
            'project_id' => $mcpServer->project_id,
            'is_active' => true,
            'remote_name' => 'My Agent',
        ]);
    }

    public function test_it_creates_a_skill_successfully(): void
    {
        Configuration::setValue('mcp_api_token', self::TOKEN);
        $mcpServer = $this->createMcpServer();

        $response = $this->postJson('/api/mcp/resources', [
            'mcp_url' => self::MCP_URL,
            'type' => 'skill',
            'slug' => 'my-mcp-server-my-skill',
            'name' => 'My Skill',
            'description' => 'A test skill',
            'instructions' => 'How to handle customer data.',
            'musts' => ['validate input', 'log actions'],
            'donts' => ['expose secrets', 'modify data'],
            'if_this_then' => ['privacy_request' => 'escalate'],
            'tools' => ['SendEmail', 'QueryDatabase'],
            'is_active' => true,
        ], $this->validHeaders());

        $response->assertStatus(201);
        $response->assertJson([
            'message' => 'Skill created successfully.',
            'action' => 'created',
            'name' => 'My Skill',
            'slug' => 'my-mcp-server-my-skill',
            'mcp_name' => 'Test MCP Server',
        ]);
        $this->assertTrue($response->json('id') > 0);

        $this->assertDatabaseHas('agent_skills', [
            'name' => 'My Skill',
            'slug' => 'my-mcp-server-my-skill',
            'mcp_server_id' => $mcpServer->id,
            'is_active' => true,
            'remote_name' => 'My Skill',
        ]);
    }

    public function test_it_upserts_a_pipeline_on_second_request(): void
    {
        Configuration::setValue('mcp_api_token', self::TOKEN);
        $mcpServer = $this->createMcpServer();

        $firstResponse = $this->postJson('/api/mcp/resources', [
            'mcp_url' => self::MCP_URL,
            'type' => 'pipeline',
            'slug' => 'upsert-pipeline',
            'name' => 'Original Name',
            'description' => 'Original description',
        ], $this->validHeaders());

        $firstResponse->assertStatus(201);
        $firstId = $firstResponse->json('id');

        $secondResponse = $this->postJson('/api/mcp/resources', [
            'mcp_url' => self::MCP_URL,
            'type' => 'pipeline',
            'slug' => 'upsert-pipeline',
            'name' => 'Updated Name',
            'description' => 'Updated description',
        ], $this->validHeaders());

        $secondResponse->assertStatus(200);
        $secondResponse->assertJson([
            'message' => 'Pipeline updated successfully.',
            'action' => 'updated',
            'name' => 'Updated Name',
            'slug' => 'upsert-pipeline',
            'id' => $firstId,
            'mcp_name' => 'Test MCP Server',
        ]);

        $this->assertDatabaseHas('pipelines', [
            'id' => $firstId,
            'name' => 'Updated Name',
            'slug' => 'upsert-pipeline',
            'mcp_server_id' => $mcpServer->id,
            'project_id' => $mcpServer->project_id,
        ]);
    }

    public function test_it_upserts_an_endpoint_on_second_request(): void
    {
        Configuration::setValue('mcp_api_token', self::TOKEN);
        $mcpServer = $this->createMcpServer();

        $firstResponse = $this->postJson('/api/mcp/resources', [
            'mcp_url' => self::MCP_URL,
            'type' => 'endpoint',
            'slug' => 'upsert-endpoint',
            'name' => 'Original Endpoint',
        ], $this->validHeaders());

        $firstResponse->assertStatus(201);
        $firstId = $firstResponse->json('id');

        $secondResponse = $this->postJson('/api/mcp/resources', [
            'mcp_url' => self::MCP_URL,
            'type' => 'endpoint',
            'slug' => 'upsert-endpoint',
            'name' => 'Updated Endpoint',
            'description' => 'New description',
        ], $this->validHeaders());

        $secondResponse->assertStatus(200);
        $secondResponse->assertJson([
            'message' => 'Endpoint updated successfully.',
            'action' => 'updated',
            'name' => 'Updated Endpoint',
            'id' => $firstId,
        ]);

        $this->assertDatabaseHas('endpoints', [
            'id' => $firstId,
            'name' => 'Updated Endpoint',
            'project_id' => $mcpServer->project_id,
        ]);
    }

    public function test_it_upserts_an_agent_on_second_request(): void
    {
        Configuration::setValue('mcp_api_token', self::TOKEN);
        $mcpServer = $this->createMcpServer();

        $firstResponse = $this->postJson('/api/mcp/resources', [
            'mcp_url' => self::MCP_URL,
            'type' => 'agent',
            'slug' => 'upsert-agent',
            'name' => 'Original Agent',
            'instructions' => 'Old instructions',
        ], $this->validHeaders());

        $firstResponse->assertStatus(201);
        $firstId = $firstResponse->json('id');

        $secondResponse = $this->postJson('/api/mcp/resources', [
            'mcp_url' => self::MCP_URL,
            'type' => 'agent',
            'slug' => 'upsert-agent',
            'name' => 'Updated Agent',
            'instructions' => 'New instructions',
        ], $this->validHeaders());

        $secondResponse->assertStatus(200);
        $secondResponse->assertJson([
            'message' => 'Agent updated successfully.',
            'action' => 'updated',
            'name' => 'Updated Agent',
            'id' => $firstId,
        ]);

        $this->assertDatabaseHas('agents', [
            'id' => $firstId,
            'name' => 'Updated Agent',
            'instructions' => 'New instructions',
            'project_id' => $mcpServer->project_id,
        ]);
    }

    public function test_it_upserts_a_skill_on_second_request(): void
    {
        Configuration::setValue('mcp_api_token', self::TOKEN);
        $mcpServer = $this->createMcpServer();

        $firstResponse = $this->postJson('/api/mcp/resources', [
            'mcp_url' => self::MCP_URL,
            'type' => 'skill',
            'slug' => 'upsert-skill',
            'name' => 'Original Skill',
            'instructions' => 'Old skill instructions',
        ], $this->validHeaders());

        $firstResponse->assertStatus(201);
        $firstId = $firstResponse->json('id');

        $secondResponse = $this->postJson('/api/mcp/resources', [
            'mcp_url' => self::MCP_URL,
            'type' => 'skill',
            'slug' => 'upsert-skill',
            'name' => 'Updated Skill',
            'instructions' => 'New skill instructions',
        ], $this->validHeaders());

        $secondResponse->assertStatus(200);
        $secondResponse->assertJson([
            'message' => 'Skill updated successfully.',
            'action' => 'updated',
            'name' => 'Updated Skill',
            'id' => $firstId,
        ]);

        $this->assertDatabaseHas('agent_skills', [
            'id' => $firstId,
            'name' => 'Updated Skill',
            'instructions' => 'New skill instructions',
        ]);
    }

    public function test_it_lists_resources_for_an_mcp_server(): void
    {
        Configuration::setValue('mcp_api_token', self::TOKEN);
        $mcpServer = $this->createMcpServer();

        Pipeline::create([
            'mcp_server_id' => $mcpServer->id,
            'name' => 'Test Pipeline',
            'slug' => 'test-pipeline',
            'is_active' => true,
        ]);

        Agent::create([
            'mcp_server_id' => $mcpServer->id,
            'remote_name' => 'Test Agent',
            'name' => 'Test Agent',
            'slug' => 'test-agent',
            'is_active' => true,
        ]);

        Endpoint::create([
            'mcp_server_id' => $mcpServer->id,
            'name' => 'Test Endpoint',
            'slug' => 'test-endpoint',
            'is_active' => true,
        ]);

        AgentSkill::create([
            'mcp_server_id' => $mcpServer->id,
            'remote_name' => 'Test Skill',
            'name' => 'Test Skill',
            'slug' => 'test-skill',
            'is_active' => true,
        ]);

        Schedule::create([
            'mcp_server_id' => $mcpServer->id,
            'name' => 'Test Schedule',
            'slug' => 'test-schedule',
            'model' => Pipeline::class,
            'model_id' => 1,
            'active' => true,
        ]);

        WebhookSubscription::create([
            'mcp_server_id' => $mcpServer->id,
            'url' => 'https://example.com/webhook',
            'slug' => 'test-webhook',
            'secret' => 'test-secret',
            'events' => [],
            'is_active' => true,
        ]);

        McpActionable::create([
            'mcp_server_id' => $mcpServer->id,
            'name' => 'Test Actionable',
            'remote_class' => 'TestClass',
            'remote_method' => 'testMethod',
            'ai_tool' => true,
        ]);

        $response = $this->getJson('/api/mcp/resources?mcp_url='.urlencode(self::MCP_URL), $this->validHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'mcp_name' => 'Test MCP Server',
        ]);

        $resources = $response->json('resources');

        $this->assertCount(1, $resources['pipelines']);
        $this->assertEquals('Test Pipeline', $resources['pipelines'][0]['name']);
        $this->assertEquals('test-pipeline', $resources['pipelines'][0]['slug']);

        $this->assertCount(1, $resources['agents']);
        $this->assertEquals('Test Agent', $resources['agents'][0]['name']);
        $this->assertEquals('test-agent', $resources['agents'][0]['slug']);

        $this->assertCount(1, $resources['endpoints']);
        $this->assertEquals('Test Endpoint', $resources['endpoints'][0]['name']);

        $this->assertCount(1, $resources['skills']);
        $this->assertEquals('Test Skill', $resources['skills'][0]['name']);

        $this->assertCount(1, $resources['schedules']);
        $this->assertEquals('Test Schedule', $resources['schedules'][0]['name']);

        $this->assertCount(1, $resources['webhooks']);
        $this->assertEquals('test-webhook', $resources['webhooks'][0]['slug']);

        $this->assertCount(1, $resources['actionables']);
        $this->assertEquals('Test Actionable', $resources['actionables'][0]['name']);
    }

    public function test_list_returns_empty_arrays_for_mcp_server_with_no_resources(): void
    {
        Configuration::setValue('mcp_api_token', self::TOKEN);
        $this->createMcpServer();

        $response = $this->getJson('/api/mcp/resources?mcp_url='.urlencode(self::MCP_URL), $this->validHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'mcp_name' => 'Test MCP Server',
        ]);

        $resources = $response->json('resources');

        $this->assertEmpty($resources['pipelines']);
        $this->assertEmpty($resources['endpoints']);
        $this->assertEmpty($resources['webhooks']);
        $this->assertEmpty($resources['schedules']);
        $this->assertEmpty($resources['agents']);
        $this->assertEmpty($resources['skills']);
        $this->assertEmpty($resources['actionables']);
    }

    public function test_list_rejects_requests_without_token(): void
    {
        $response = $this->getJson('/api/mcp/resources?mcp_url='.urlencode(self::MCP_URL));

        $response->assertStatus(401);
        $response->assertJson(['message' => 'MCP API token not configured.']);
    }

    public function test_list_rejects_requests_with_invalid_token(): void
    {
        Configuration::setValue('mcp_api_token', self::TOKEN);

        $response = $this->getJson('/api/mcp/resources?mcp_url='.urlencode(self::MCP_URL), [
            'Authorization' => 'Bearer wrong-token',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Unauthorized.']);
    }

    public function test_list_returns_404_when_mcp_server_not_found(): void
    {
        Configuration::setValue('mcp_api_token', self::TOKEN);

        $response = $this->getJson('/api/mcp/resources?mcp_url=https://unknown-server.com', $this->validHeaders());

        $response->assertStatus(404);
        $response->assertJson(['message' => 'MCP server not found.']);
    }

    public function test_list_only_returns_resources_for_the_specified_mcp_server(): void
    {
        Configuration::setValue('mcp_api_token', self::TOKEN);
        $mcpServer = $this->createMcpServer();

        $otherMcpServer = McpServer::create([
            'name' => 'Other MCP Server',
            'url' => 'https://other-server.com',
            'transport_type' => 'http',
            'status' => 'active',
        ]);

        Pipeline::create([
            'mcp_server_id' => $mcpServer->id,
            'name' => 'My Pipeline',
            'slug' => 'my-pipeline',
            'is_active' => true,
        ]);

        Pipeline::create([
            'mcp_server_id' => $otherMcpServer->id,
            'name' => 'Other Pipeline',
            'slug' => 'other-pipeline',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/mcp/resources?mcp_url='.urlencode(self::MCP_URL), $this->validHeaders());

        $response->assertStatus(200);

        $resources = $response->json('resources');
        $this->assertCount(1, $resources['pipelines']);
        $this->assertEquals('My Pipeline', $resources['pipelines'][0]['name']);
        $this->assertCount(0, array_filter($resources['pipelines'], fn ($p) => $p['name'] === 'Other Pipeline'));
    }
}
