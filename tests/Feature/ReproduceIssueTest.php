<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\ChatWidget\Models\AgentWidget;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Models\McpServer;
use Agentoom\Core\Models\Provider;
use Agentoom\Core\Models\ProviderModel;
use Agentoom\Core\Services\ExportService;
use Agentoom\Core\Services\ImportService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ReproduceIssueTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_export_automatically_includes_agent(): void
    {
        // 1. Setup: Create an agent and a widget linked to it
        $agent = Agent::create([
            'name' => 'Linked Agent',
            'slug' => 'linked-agent',
        ]);

        $widget = AgentWidget::create([
            'agent_id' => $agent->id,
            'title' => 'Test Widget',
            'slug' => 'test-widget-slug',
            'position' => 'bottom-right',
            'settings' => [],
        ]);

        // 2. Export ONLY the widget
        $exportService = app(ExportService::class);
        $data = $exportService->export(['widgets' => [$widget->id]]);

        // 3. Assertion: Agent should be automatically included in the export
        $this->assertCount(1, $data['widgets']);
        $this->assertArrayHasKey('agents', $data);
        $this->assertCount(1, $data['agents']);
        $this->assertEquals('linked-agent', $data['agents'][0]['slug']);

        // 4. Import into a clean system
        $widget->delete();
        $agent->delete();

        $importService = app(ImportService::class);
        $importService->import($data);

        // 5. Assertion: Widget AND Agent should be imported
        $this->assertDatabaseHas('agents', ['slug' => 'linked-agent']);
        $this->assertDatabaseHas('agent_widgets', ['slug' => 'test-widget-slug']);
    }

    public function test_agent_config_exports_effective_config_if_empty(): void
    {
        // 1. Setup: Global config
        $provider = Provider::create(['name' => 'global-openai', 'api_key' => 'global-key', 'url' => 'https://api.openai.com/v1']);
        $model = ProviderModel::create([
            'provider_id' => $provider->id,
            'model_name' => 'gpt-4-global',
            'model_features' => [],
            'is_active' => true,
        ]);

        Configuration::setValue('ai_provider', $provider->id);
        Configuration::setValue('ai_model', $model->id);

        $agent = Agent::create([
            'name' => 'Default Agent',
            'slug' => 'default-agent',
            'config' => [], // Empty config, should use global
        ]);

        // 2. Export
        $exportService = app(ExportService::class);
        $data = $exportService->export(['agents' => [$agent->id]]);

        // 3. Assertion: Exported config should contain global defaults
        $agentData = $data['agents'][0];
        $this->assertEquals('global-openai', $agentData['config']['provider_id']);
        $this->assertEquals('gpt-4-global', $agentData['config']['provider_model_id']);

        // 4. Import into system with DIFFERENT global config (or no config)
        $agent->delete();
        Configuration::setValue('ai_provider', null);
        Configuration::setValue('ai_model', null);

        $importService = app(ImportService::class);
        $importService->import($data);

        // 5. Assertion: Imported agent should have the config from the export (previously global)
        $importedAgent = Agent::where('slug', 'default-agent')->first();
        $this->assertNotNull($importedAgent);
        $this->assertEquals($provider->id, $importedAgent->config['provider_id']);
        $this->assertEquals($model->id, $importedAgent->config['provider_model_id']);
    }

    public function test_mcp_server_resolution(): void
    {
        // 1. Setup
        $mcpServer = McpServer::create([
            'name' => 'Test MCP',
            'url' => 'http://localhost:3000',
        ]);

        $agent = Agent::create([
            'name' => 'MCP Agent',
            'slug' => 'mcp-agent',
            'mcp_server_id' => $mcpServer->id,
            'remote_name' => 'remote-agent',
        ]);

        // 2. Export
        $exportService = app(ExportService::class);
        $data = $exportService->export(['agents' => [$agent->id]]);

        $this->assertEquals('Test MCP', $data['agents'][0]['mcp_server']);
        $this->assertEquals('remote-agent', $data['agents'][0]['remote_name']);

        // 3. Import
        $agent->delete();
        // Keep MCP server in DB or it won't resolve during import

        $importService = app(ImportService::class);
        $importService->import($data);

        // 4. Assertion
        $importedAgent = Agent::where('slug', 'mcp-agent')->first();
        $this->assertNotNull($importedAgent);
        $this->assertEquals($mcpServer->id, $importedAgent->mcp_server_id);
        $this->assertEquals('remote-agent', $importedAgent->remote_name);
    }
}
