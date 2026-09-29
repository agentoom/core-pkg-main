<?php

namespace Agentoom\Core\Services;

use Agentoom\Core\Models\McpActionable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class McpActionableService
{
    /**
     * Execute a remote actionable via HTTP POST to /agentoom/actionable.
     */
    public function execute(McpActionable $actionable, array $parameters = []): mixed
    {
        $server = $actionable->mcpServer;
        $baseUrl = $this->deriveBaseUrl($actionable->remote_base_url ?: $server->url);
        $endpoint = rtrim($baseUrl, '/').'/agentoom/actionable';

        Log::info("Executing remote actionable [{$actionable->name}] on [{$endpoint}]", [
            'class' => $actionable->remote_class,
            'method' => $actionable->remote_method,
            'parameters' => $parameters,
        ]);

        try {
            $response = Http::withHeaders([
                'Origin' => config('app.url'),
            ])
                ->timeout(60)
                ->post($endpoint, [
                    'class' => $actionable->remote_class,
                    'method' => $actionable->remote_method,
                    'parameters' => $parameters,
                ]);
        } catch (ConnectionException $e) {
            Log::error("Remote actionable connection failed to [{$endpoint}]: ".$e->getMessage());
            throw new \Exception("Could not connect to remote actionable endpoint [{$endpoint}]: ".$e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            $error = $response->json('error') ?? "Remote execution failed with status {$response->status()}";
            Log::error("Remote actionable [{$actionable->name}] failed on [{$endpoint}]: {$error}", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception($error);
        }

        $result = $response->json('result');
        Log::info("Remote actionable [{$actionable->name}] succeeded", ['result' => $result]);

        return $result;
    }

    /**
     * Derive base URL (scheme + host + port) from a full URL.
     */
    protected function deriveBaseUrl(string $url): string
    {
        $parts = parse_url($url);
        $baseUrl = ($parts['scheme'] ?? 'http').'://'.($parts['host'] ?? 'localhost');
        if (isset($parts['port'])) {
            $baseUrl .= ':'.$parts['port'];
        }

        return $baseUrl;
    }
}
