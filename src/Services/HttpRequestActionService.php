<?php

namespace Agentoom\Core\Services;

use Agentoom\Core\Attributes\Actionable;
use Agentoom\Core\Attributes\GroupVisibility;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

#[GroupVisibility('agentoom-core')]
class HttpRequestActionService
{
    /**
     * Send an HTTP request to any endpoint with configurable method, URL, headers, and body.
     *
     * @param  string  $method  The HTTP method to use (GET, POST, PUT, PATCH, DELETE).
     * @param  string  $url  The full endpoint URL to send the request to.
     * @param  array  $headers  Optional HTTP headers as key-value pairs (e.g. {"Authorization": "Bearer token", "Content-Type": "application/json"}).
     * @param  array|string  $body  The request body. Can be an array (sent as JSON) or a raw string.
     * @return string The response body as a string (JSON decoded if possible, otherwise raw text).
     */
    #[Actionable(
        name: 'Send HTTP Request',
        description: 'Send an HTTP request to any endpoint. Specify the HTTP method, URL, optional headers, and request body. The body can be any JSON structure defined via the JSON Structure Helper.',
        category: 'HTTP / API'
    )]
    public function sendRequest(string $method, string $url, array $headers = [], array|string $body = []): string
    {
        $method = strtoupper($method);

        Log::debug('HttpRequestActionService sendRequest', [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => is_array($body) ? json_encode($body) : $body,
        ]);

        // Validate URL
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return 'Error: Invalid URL provided. Please provide a valid HTTP or HTTPS URL.';
        }

        // Only allow HTTP/HTTPS
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (! in_array($scheme, ['http', 'https'])) {
            return 'Error: Only HTTP and HTTPS protocols are allowed.';
        }

        // Validate method
        $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];
        if (! in_array($method, $allowedMethods)) {
            return 'Error: Unsupported HTTP method "'.$method.'". Allowed methods: '.implode(', ', $allowedMethods).'.';
        }

        // Build the request payload section (used in both success and error output)
        $payloadOutput = '';
        if (! empty($headers)) {
            $payloadOutput .= "Headers:\n".json_encode($headers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n\n";
        }
        $bodyDisplay = is_array($body) ? json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $body;
        if (! empty($bodyDisplay) && $bodyDisplay !== '[]' && $bodyDisplay !== '{}') {
            $payloadOutput .= "Body:\n{$bodyDisplay}\n\n";
        }

        try {
            $pendingRequest = Http::timeout(60)->withHeaders($headers);

            // When body is a raw string (e.g. already-JSON), send it as raw content
            // to avoid double-encoding. Arrays are sent as JSON automatically.
            if (is_string($body) && ! empty($body) && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                $pendingRequest->withBody($body, $headers['Content-Type'] ?? 'application/json');
            }

            $response = match ($method) {
                'GET' => $pendingRequest->get($url, is_array($body) ? $body : []),
                'HEAD' => $pendingRequest->head($url),
                'OPTIONS' => $pendingRequest->send('OPTIONS', $url, ['query' => is_array($body) ? $body : []]),
                'POST' => is_string($body) && ! empty($body) ? $pendingRequest->post($url) : $pendingRequest->post($url, $body),
                'PUT' => is_string($body) && ! empty($body) ? $pendingRequest->put($url) : $pendingRequest->put($url, $body),
                'PATCH' => is_string($body) && ! empty($body) ? $pendingRequest->patch($url) : $pendingRequest->patch($url, $body),
                'DELETE' => is_string($body) && ! empty($body) ? $pendingRequest->delete($url) : $pendingRequest->delete($url, $body),
            };

            $responseBody = $response->body();
            $statusCode = $response->status();

            // Try to return pretty-printed JSON if possible
            $decoded = json_decode($responseBody, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $responseBody = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }

            Log::debug('HttpRequestActionService response', [
                'status' => $statusCode,
                'body_length' => strlen($responseBody),
            ]);

            // Limit response size
            $maxLength = 50000;
            if (strlen($responseBody) > $maxLength) {
                $responseBody = substr($responseBody, 0, $maxLength)."\n\n[Response truncated due to length...]";
            }

            $output = "HTTP {$method} {$url}\n".
                "Status: {$statusCode}\n\n";

            if (! empty($payloadOutput)) {
                $output .= "--- Request Payload ---\n\n".$payloadOutput;
            }

            $output .= "--- Response Start ---\n\n".
                $responseBody."\n\n".
                '--- Response End ---';

            return $output;

        } catch (\Throwable $e) {
            Log::error('HttpRequestActionService error: '.$e->getMessage(), [
                'method' => $method,
                'url' => $url,
                'exception' => $e,
            ]);

            $errorOutput = "HTTP {$method} {$url}\n".
                'Error: '.$e->getMessage()."\n\n";

            if (! empty($payloadOutput)) {
                $errorOutput .= "--- Request Payload ---\n\n".$payloadOutput;
            }

            return $errorOutput;
        }
    }
}
