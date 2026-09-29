<?php

namespace Agentoom\Core\Ai\Tools\Public;

use Agentoom\Core\Attributes\GroupVisibility;
use Agentoom\Core\Attributes\Sentinelable;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Services\Api\GenericApiService;
use Agentoom\Core\Traits\HandlesToolActions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

#[GroupVisibility('agentoom-core')]
class ApiTool implements Tool
{
    use HandlesToolActions;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Perform HTTP requests to external APIs with support for various methods and authentication types.';
    }

    /**
     * Perform a GET request to an external API.
     */
    #[Sentinelable(
        name: 'API Tool GET Request',
        description: 'Perform a GET (read) HTTP request to an external API endpoint.',
        category: 'API'
    )]
    public function get(Request $request): Stringable|string
    {
        return $this->executeWithMethod($request, 'GET');
    }

    /**
     * Perform a POST request to an external API.
     */
    #[Sentinelable(
        name: 'API Tool POST Request',
        description: 'Perform a POST (create) HTTP request to an external API endpoint.',
        category: 'API'
    )]
    public function post(Request $request): Stringable|string
    {
        return $this->executeWithMethod($request, 'POST');
    }

    /**
     * Perform a PUT request to an external API.
     */
    #[Sentinelable(
        name: 'API Tool PUT Request',
        description: 'Perform a PUT (replace) HTTP request to an external API endpoint.',
        category: 'API'
    )]
    public function put(Request $request): Stringable|string
    {
        return $this->executeWithMethod($request, 'PUT');
    }

    /**
     * Perform a PATCH request to an external API.
     */
    #[Sentinelable(
        name: 'API Tool PATCH Request',
        description: 'Perform a PATCH (partial update) HTTP request to an external API endpoint.',
        category: 'API'
    )]
    public function patch(Request $request): Stringable|string
    {
        return $this->executeWithMethod($request, 'PATCH');
    }

    /**
     * Perform a DELETE request to an external API.
     */
    #[Sentinelable(
        name: 'API Tool DELETE Request',
        description: 'Perform a DELETE (destroy) HTTP request to an external API endpoint.',
        category: 'API'
    )]
    public function delete(Request $request): Stringable|string
    {
        return $this->executeWithMethod($request, 'DELETE');
    }

    /**
     * Perform a HEAD request to an external API.
     */
    #[Sentinelable(
        name: 'API Tool HEAD Request',
        description: 'Perform a HEAD HTTP request to an external API endpoint.',
        category: 'API'
    )]
    public function head(Request $request): Stringable|string
    {
        return $this->executeWithMethod($request, 'HEAD');
    }

    /**
     * Perform an OPTIONS request to an external API.
     */
    #[Sentinelable(
        name: 'API Tool OPTIONS Request',
        description: 'Perform an OPTIONS HTTP request to an external API endpoint.',
        category: 'API'
    )]
    public function options(Request $request): Stringable|string
    {
        return $this->executeWithMethod($request, 'OPTIONS');
    }

    /**
     * Internal dispatcher: execute the request with the given HTTP method.
     */
    protected function executeWithMethod(Request $request, string $method): Stringable|string
    {
        if ($error = $this->checkForForbiddenSecurityTerms($request)) {
            return $error;
        }

        try {
            $config = $this->resolveConfig($request, $method);

            $result = app(GenericApiService::class)->execute($config);

            return json_encode($result, JSON_PRETTY_PRINT);
        } catch (\Throwable $e) {
            return 'Error: '.$e->getMessage();
        }
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['get', 'post', 'put', 'patch', 'delete', 'head', 'options'])
                ->required()
                ->description('The HTTP action to perform: get, post, put, patch, delete, head, or options.'),

            'url' => $schema->string()
                ->required()
                ->description('The full URL of the API endpoint.'),

            'headers' => $schema->object()
                ->description('Optional HTTP headers as key-value pairs.'),

            'params' => $schema->object()
                ->description('Optional query parameters for GET requests or request body for other methods.'),

            'auth_type' => $schema->string()
                ->enum(['none', 'basic', 'bearer', 'api_key_header', 'api_key_query'])
                ->description('Authentication type (default: none).'),

            'auth_resources' => $schema->object([
                'username' => $schema->string()->description('Username for basic auth.'),
                'password' => $schema->string()->description('Password for basic auth.'),
                'token' => $schema->string()->description('Bearer token.'),
                'key' => $schema->string()->description('API key value.'),
                'name' => $schema->string()->description('Header name or query parameter name for API key (e.g., X-API-Key, api_key).'),
            ])->description('Resources for authentication. Can be provided directly or mapped from a resource.'),

            'resource_id' => $schema->string()
                ->description('Optional resource ID to fetch resources from.'),

            'resource_mapping' => $schema->object()
                ->description('Mapping for resource fields to auth_resources or other config fields.'),
        ];
    }

    /**
     * Resolve the resource from the request and user context.
     */
    protected function getResource(Request $request): ?Resource
    {
        $resourceId = (string) $request->string('resource_id');

        $user = auth()->user();

        if (! $user || trim($resourceId) === '') {
            return null;
        }

        if (str_starts_with($resourceId, 'global:')) {
            $id = substr($resourceId, 7);

            return Resource::visibleTo($user)
                ->with(['resourceType', 'user'])
                ->find($id);
        }

        return Resource::visibleTo($user)
            ->with(['resourceType', 'user'])
            ->find($resourceId);
    }

    /**
     * Map resource data using the schema/mapping provided by the agent.
     */
    protected function mapResourceData(Resource $resource, Request $request): array
    {
        $mapping = $this->getCleanParams($request, 'resource_mapping');

        $mapped = [];
        $context = [
            'data' => $resource->getDecryptedData(),
            'resource' => $resource->toArray(),
            'name' => $resource->name,
            'description' => $resource->description,
        ];

        foreach ($mapping as $targetKey => $sourcePath) {
            if (! is_string($sourcePath)) {
                continue;
            }

            if (preg_match('/\[ENCRYPTED_REFERENCE:([a-zA-Z0-9_\-]+)\]/', $sourcePath, $matches)) {
                $key = $matches[1];
                $value = $resource->getDecryptedData()[$key] ?? null;
            } else {
                $value = data_get($context, $sourcePath);

                if ($value === null && ! str_contains($sourcePath, '.')) {
                    $value = data_get($context, "data.{$sourcePath}");
                }
            }

            if ($value !== null) {
                $mapped[$targetKey] = $value;
            }
        }

        return $this->resolveEncryptedReferences($mapped, $resource);
    }

    /**
     * Resolve any encrypted references in an array using the provided resource.
     */
    protected function resolveEncryptedReferences(array $data, Resource $resource): array
    {
        $resourceData = $resource->getDecryptedData();

        array_walk_recursive($data, function (&$value) use ($resourceData) {
            if (is_string($value) && preg_match('/\[ENCRYPTED_REFERENCE:([a-zA-Z0-9_\-]+)\]/', $value, $matches)) {
                $key = $matches[1];
                if (isset($resourceData[$key])) {
                    $value = $resourceData[$key];
                }
            }
        });

        return $data;
    }

    /**
     * Get and clean parameters from the request, handling various common LLM input formats.
     */
    protected function getCleanParams(Request $request, string $key = 'params', bool $mergeObjects = true): array
    {
        $params = $request->all($key)[$key] ?? [];

        if (is_string($params)) {
            $decoded = json_decode($params, true);
            if (is_array($decoded)) {
                $params = $decoded;
            }
        }

        if (! is_array($params)) {
            $params = $request->array($key);
        }

        if (count($params) === 1 && isset($params[0]) && is_string($params[0])) {
            $decoded = json_decode($params[0], true);
            if (is_array($decoded)) {
                $params = $decoded;
            }
        }

        if ($mergeObjects && array_is_list($params) && count($params) > 0 && is_array($params[0])) {
            $merged = [];
            foreach ($params as $p) {
                if (is_array($p)) {
                    $merged = array_merge($merged, $p);
                }
            }

            $params = $merged;
        }

        $resourceId = (string) $request->string('resource_id');
        if ($key !== 'resource_mapping' && $resourceId !== '') {
            $resource = $this->getResource($request);
            if ($resource) {
                $params = $this->resolveEncryptedReferences($params, $resource);
            }
        }

        return $params;
    }

    /**
     * Resolve the final configuration for the API request.
     */
    protected function resolveConfig(Request $request, string $method = 'GET'): array
    {
        $config = [
            'method' => $method,
            'url' => $request->string('url'),
            'headers' => $this->getCleanParams($request, 'headers'),
            'params' => $this->getCleanParams($request, 'params'),
            'auth_type' => $request->string('auth_type', 'none'),
            'auth_resources' => $this->getCleanParams($request, 'auth_resources'),
        ];

        $resource = $this->getResource($request);
        if ($resource) {
            $mapped = $this->mapResourceData($resource, $request);

            foreach ($mapped as $key => $value) {
                if (in_array($key, ['username', 'password', 'token', 'key', 'name'])) {
                    $config['auth_resources'][$key] = $value;
                } else {
                    $config[$key] = $value;
                }
            }
        }

        return $config;
    }
}
