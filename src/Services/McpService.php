<?php

namespace Agentoom\Core\Services;

use Agentoom\Core\Models\McpServer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class McpService
{
    /**
     * List tools available on the MCP server.
     */
    public function listTools(McpServer $server, ?string $cursor = null): array
    {
        return $this->sendRequest($server, 'tools/list', array_filter(['cursor' => $cursor]));
    }

    /**
     * Call a tool on the MCP server.
     */
    public function callTool(McpServer $server, string $name, array $arguments = []): array
    {
        return $this->sendRequest($server, 'tools/call', [
            'name' => $name,
            'arguments' => $arguments,
        ]);
    }

    /**
     * List resources available on the MCP server.
     */
    public function listResources(McpServer $server, ?string $cursor = null): array
    {
        return $this->sendRequest($server, 'resources/list', array_filter(['cursor' => $cursor]));
    }

    /**
     * Get Agentoom elements (agents and skills) from the MCP server.
     */
    public function getAgentoomElements(McpServer $server): array
    {
        return $this->sendRequest($server, 'agentoom/elements', [], true);
    }

    /**
     * Get Actionables from the MCP server.
     */
    public function getActionables(McpServer $server): array
    {
        return $this->sendRequest($server, 'agentoom/actionables', [], true);
    }

    /**
     * Get privacy rules from the MCP server.
     */
    public function getAgentoomPrivacyRecognizers(McpServer $server): array
    {
        return $this->sendRequest($server, 'agentoom/privacy-recognizers', [], true);
    }

    /**
     * Send a JSON-RPC request to the MCP server.
     */
    public function sendRequest(McpServer $server, string $method, array $params, bool $optional = false): array
    {
        $id = uniqid();
        $payload = [
            'jsonrpc' => '2.0',
            'id' => $id,
            'method' => $method,
            'params' => $params,
        ];

        Log::info("MCP request → [{$server->name}] {$method}", [
            'url' => $server->url,
            'params' => $params,
        ]);

        try {
            // For now, we support simple HTTP POST as the transport.
            // If SSE is used, we assume the server provides a POST endpoint at the same URL or similar.
            // In a full SSE implementation, we would first connect to GET /sse and get the endpoint.
            $http = Http::timeout(60);

            if ($server->auth_type === 'bearer' && $server->auth_token) {
                $http = $http->withToken($server->auth_token);
            } elseif ($server->auth_type === 'api_key' && $server->auth_token) {
                $headerName = $server->auth_header_name ?: 'X-API-Key';
                $http = $http->withHeaders([$headerName => $server->auth_token]);
            } elseif ($server->auth_type === 'basic' && $server->auth_username) {
                $http = $http->withBasicAuth($server->auth_username, $server->auth_password ?? '');
            }

            $response = $http->post($server->url, $payload);

            Log::debug("MCP response ← [{$server->name}]", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if ($response->failed()) {
                $responseBody = $response->body();

                if ($optional && $response->status() >= 400 && $response->status() < 500) {
                    Log::info("MCP optional method skipped [{$server->name}] {$method}", [
                        'status' => $response->status(),
                        'body' => $responseBody,
                    ]);

                    return [];
                }

                $detail = '';

                if ($response->status() === 403) {
                    $detail = ' (403 Forbidden — check server Origin/IP allowlist or API key)';
                }

                throw new \Exception('MCP Server returned error: '.$response->status().$detail.' [body: '.Str::limit($responseBody, 500).']');
            }

            $data = $response->json();

            if (! is_array($data)) {
                throw new \Exception('Invalid JSON response from MCP server: '.$response->body());
            }

            if (isset($data['error'])) {
                if ($optional && ($data['error']['code'] ?? 0) === -32601) {
                    Log::debug("Optional MCP method [{$method}] not implemented on [{$server->url}]");

                    return [];
                }
                throw new \Exception('MCP JSON-RPC Error: '.($data['error']['message'] ?? 'Unknown error'));
            }

            return $data['result'] ?? [];
        } catch (\Exception $e) {
            if ($optional) {
                Log::debug("Optional MCP method [{$method}] failed gracefully for [{$server->name}]: ".$e->getMessage());

                return [];
            }
            $message = $e->getMessage();

            if (str_contains($message, 'Failed to connect to localhost') || str_contains($message, 'Failed to connect to 127.0.0.1')) {
                $message .= ' (Hint: If you are running in Docker, try using "host.docker.internal" instead of "localhost")';
            }

            Log::error("MCP Service Error [{$server->name}]: ".$message);

            $server->update([
                'status' => 'error',
                'error_message' => $message,
            ]);

            throw new \Exception($message, $e->getCode(), $e);
        }
    }
}
