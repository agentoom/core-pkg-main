<?php

namespace Agentoom\Core\Services\Api;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class GenericApiService
{
    /**
     * Execute an HTTP request.
     */
    public function execute(array $config): array
    {
        $method = strtoupper($config['method'] ?? 'GET');
        $url = $config['url'];
        $headers = $config['headers'] ?? [];
        $params = $config['params'] ?? [];
        $authType = $config['auth_type'] ?? 'none';
        $authResources = $config['auth_resources'] ?? [];

        $pendingRequest = Http::asJson()->withHeaders($headers);

        $pendingRequest = $this->applyAuth($pendingRequest, $authType, $authResources);

        /** @var Response $response */
        $response = match ($method) {
            'GET' => $pendingRequest->get($url, $params),
            'POST' => $pendingRequest->post($url, $params),
            'PUT' => $pendingRequest->put($url, $params),
            'PATCH' => $pendingRequest->patch($url, $params),
            'DELETE' => $pendingRequest->delete($url, $params),
            'HEAD' => $pendingRequest->head($url, $params),
            'OPTIONS' => $pendingRequest->send('OPTIONS', $url, ['query' => $params]),
            default => throw new \Exception("Unsupported HTTP method: {$method}"),
        };

        return [
            'status' => $response->status(),
            'body' => $response->json() ?: $response->body(),
            'headers' => $response->headers(),
        ];
    }

    /**
     * Apply authentication to the request.
     */
    protected function applyAuth($pendingRequest, string $authType, array $resources)
    {
        return match ($authType) {
            'basic' => $pendingRequest->withBasicAuth(
                $resources['username'] ?? '',
                $resources['password'] ?? ''
            ),
            'bearer' => $pendingRequest->withToken($resources['token'] ?? ''),
            'api_key_header' => $pendingRequest->withHeaders([
                ($resources['name'] ?? 'X-API-Key') => $resources['key'] ?? '',
            ]),
            'api_key_query' => $pendingRequest->withQueryParameters([
                ($resources['name'] ?? 'api_key') => $resources['key'] ?? '',
            ]),
            'none' => $pendingRequest,
            default => $pendingRequest,
        };
    }
}
