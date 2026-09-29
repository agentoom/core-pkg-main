<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Models\McpServer;
use Agentoom\Core\Services\McpService;
use Agentoom\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class McpServiceOptionalMethodsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_handles_404_gracefully_for_optional_methods()
    {
        $server = McpServer::create([
            'name' => 'Test Server',
            'url' => 'http://localhost:8080/mcp',
            'transport_type' => 'http',
            'status' => 'active',
        ]);

        Http::fake([
            'http://localhost:8080/mcp' => Http::response(['error' => 'Not Found'], 404),
        ]);

        $mcpService = app(McpService::class);

        // This should not throw an exception
        $elements = $mcpService->getAgentoomElements($server);
        $this->assertEquals([], $elements);

        $actionables = $mcpService->getActionables($server);
        $this->assertEquals([], $actionables);

        // Server status should NOT be error
        $server->refresh();
        $this->assertEquals('active', $server->status);
    }

    public function test_it_handles_json_rpc_method_not_found_gracefully()
    {
        $server = McpServer::create([
            'name' => 'Test Server',
            'url' => 'http://localhost:8080/mcp',
            'transport_type' => 'http',
            'status' => 'active',
        ]);

        Http::fake([
            'http://localhost:8080/mcp' => Http::response([
                'jsonrpc' => '2.0',
                'id' => '1',
                'error' => [
                    'code' => -32601,
                    'message' => 'Method not found',
                ],
            ]),
        ]);

        $mcpService = app(McpService::class);

        $elements = $mcpService->getAgentoomElements($server);
        $this->assertEquals([], $elements);

        $actionables = $mcpService->getActionables($server);
        $this->assertEquals([], $actionables);

        $server->refresh();
        $this->assertEquals('active', $server->status);
    }

    public function test_it_throws_exception_for_required_methods()
    {
        $server = McpServer::create([
            'name' => 'Test Server',
            'url' => 'http://localhost:8080/mcp',
            'transport_type' => 'http',
            'status' => 'active',
        ]);

        Http::fake([
            'http://localhost:8080/mcp' => Http::response(['error' => 'Not Found'], 404),
        ]);

        $mcpService = app(McpService::class);

        $this->expectException(\Exception::class);
        $mcpService->listTools($server);
    }
}
