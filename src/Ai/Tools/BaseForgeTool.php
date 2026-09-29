<?php

namespace Agentoom\Core\Ai\Tools;

use GuzzleHttp\Client;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Tools\Request;
use Laravel\Forge\Exceptions\ForbiddenException;
use Laravel\Forge\Forge;
use Stringable;
use Throwable;

abstract class BaseForgeTool extends BaseResourceTool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'To be extended';
    }

    protected ?Forge $forgeInstance = null;

    /**
     * Set a custom Forge instance (useful for testing).
     */
    public function setForge(Forge $forge): void
    {
        $this->forgeInstance = $forge;
    }

    /**
     * Get the Forge instance.
     */
    protected function getForge(string $apiToken, ?string $organization = null, ?string $action = null): Forge
    {
        if ($this->forgeInstance) {
            return $this->forgeInstance;
        }

        $apiToken = trim($apiToken);

        if (str_starts_with(strtolower($apiToken), 'bearer ')) {
            $apiToken = trim(substr($apiToken, 7));
        }

        // Determine the base URI. Default to v1, but use organization context if provided.
        // The Forge V2 / Organization API typically uses /api/orgs/{id}/...
        $baseUri = 'https://forge.laravel.com/api/v1/';

        // Actions that are generally global and shouldn't be organization-prefixed.
        $globalActions = ['user', 'credentials', 'events', 'event', 'phpVersions', 'recipes'];

        if ($organization && ! in_array($action, $globalActions)) {
            // Note: Organization endpoints in Forge V2 often omit the /v1/ prefix.
            $baseUri = "https://forge.laravel.com/api/orgs/{$organization}/";
        }

        $guzzle = new Client([
            'base_uri' => $baseUri,
            'http_errors' => false,
            'headers' => [
                'Authorization' => 'Bearer '.$apiToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'User-Agent' => 'Agentoom/1.0 Forge SDK',
            ],
        ]);

        return new Forge($apiToken, $guzzle);
    }

    /**
     * Execute the Forge action using the Forge SDK.
     */
    protected function executeForgeAction(string $action, Request $request): Stringable|string
    {
        Log::debug('ForgeTool executeAction', [
            'action' => $action,
            'user_id' => auth()->id(),
        ]);

        $resource = $this->getResource($request);
        if (! $resource) {
            return 'Error: Could not resolve the specified Forge resource. Ensure you provided a valid resource_id and have permission to access it.';
        }

        $mapped = $this->mapResourceData($resource, $request);

        if ($error = $this->validateMappedData($mapped, $resource)) {
            return $error;
        }

        $apiToken = trim($mapped['api_token']);

        if (str_contains($apiToken, '[ENCRYPTED_REFERENCE:')) {
            Log::error('Forge API Token still contains an encrypted reference after mapping. This should not happen.', [
                'token_preview' => substr($apiToken, 0, 30).'...',
                'action' => $action,
            ]);
        }

        $params = $request->all();

        // Try to get organization from: 1. Tool parameters, 2. Mapped data, 3. Raw resource data
        $organization = $params['organization'] ??
            $mapped['organization'] ??
            $resource->getDecryptedData()['organization'] ??
            $resource->getDecryptedData()['org_id'] ?? // Common alternative
            null;

        $forge = $this->getForge($apiToken, $organization, $action);

        try {
            // Map common parameters to what Forge SDK expects
            $args = [];

            // This is a bit manual but necessary because SDK methods have specific signatures
            // We use the action name directly if it exists on the Forge class
            if (! method_exists($forge, $action)) {
                return "Error: Action '{$action}' is not supported by the Forge SDK.";
            }

            $reflection = new \ReflectionMethod($forge, $action);
            $args = [];
            $usedParams = ['action', 'resource_mapping', 'resource_id', 'wait', 'organization'];

            // First pass: identify which parameters are directly mapped
            foreach ($reflection->getParameters() as $parameter) {
                $name = $parameter->getName();
                $snakeName = str($name)->snake()->toString();
                if (isset($params[$snakeName])) {
                    $usedParams[] = $snakeName;
                } elseif (isset($params[$name])) {
                    $usedParams[] = $name;
                }
            }

            // Second pass: build arguments and handle the 'data' parameter fallback
            foreach ($reflection->getParameters() as $parameter) {
                $name = $parameter->getName();

                // Try different naming conventions for IDs
                $snakeName = str($name)->snake()->toString();
                $value = $params[$snakeName] ?? $params[$name] ?? null;

                // Fallback for 'data' array: collect all unused parameters
                if ($value === null && ($name === 'data' || $snakeName === 'data')) {
                    $type = $parameter->getType();
                    if ($type instanceof \ReflectionNamedType && $type->getName() === 'array') {
                        $value = array_diff_key($params, array_flip($usedParams));
                    }
                }

                if ($value === null && ! $parameter->isOptional()) {
                    return "Error: Missing required parameter '{$snakeName}' for action '{$action}'.";
                }

                $args[] = $value ?? ($parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null);
            }

            // If one of the arguments is the 'data' array, we wrap it in 'json' to force
            // the SDK to send it as JSON. This is because the Forge SDK (MakesHttpRequests)
            // defaults to form_params unless 'json' key is present in the payload.
            foreach ($reflection->getParameters() as $index => $parameter) {
                if (($parameter->getName() === 'data' || str($parameter->getName())->snake()->toString() === 'data') && is_array($args[$index])) {
                    // For organization-based site creation, we need additional mandatory fields
                    if ($action === 'createSite' && $organization) {
                        if (! isset($args[$index]['domain_mode'])) {
                            $args[$index]['domain_mode'] = 'custom';
                        }
                        if (! isset($args[$index]['www_redirect_type'])) {
                            $args[$index]['www_redirect_type'] = 'none';
                        }
                        if (! isset($args[$index]['allow_wildcard_subdomains'])) {
                            $args[$index]['allow_wildcard_subdomains'] = false;
                        }
                    }

                    $args[$index] = ['json' => $args[$index]];
                }
            }

            $result = $forge->{$action}(...$args);

            // If the result is a resource or array of resources, we might want to format it
            return $this->formatResult($result);

        } catch (Throwable $e) {
            Log::error('Forge API Error: '.$e->getMessage(), [
                'exception' => $e,
                'action' => $action,
            ]);

            $message = 'Error interacting with Forge API: '.$e->getMessage();

            if (method_exists($e, 'errors')) {
                $errors = $e->errors();
                if (is_array($errors)) {
                    $message .= "\nValidation errors:\n".json_encode($errors, JSON_PRETTY_PRINT);
                }
            }

            if (str_contains($message, '401') || str_contains($message, '403') || $e instanceof ForbiddenException) {
                if ($action === 'user') {
                    $message .= "\n\nHint: The 'user' action failed with 401/403. This might mean your API token is invalid, expired, or you are trying to access a restricted organization. Ensure your token is correct and has the necessary permissions.";
                } else {
                    $message .= "\n\nHint: This 401/403 error often indicates that the organization context is missing or the token lacks permissions for the specific resource. If you are part of an organization, you MUST provide the 'organization' parameter (ID or slug) in your request. You can find available organization IDs by calling action='user' with ForgeRetrieveTool.";
                }
            }

            return $message;
        }
    }

    /**
     * Format the result from Forge SDK.
     */
    protected function formatResult($result): string
    {
        if (is_array($result)) {
            if (empty($result)) {
                return 'No results found.';
            }

            $output = 'Found '.count($result)." items:\n";
            foreach ($result as $item) {
                $output .= '- '.$this->formatItem($item)."\n";
            }

            return $output;
        }

        if (is_object($result)) {
            return "Result:\n".$this->formatItem($result);
        }

        if (is_bool($result)) {
            return $result ? 'Success' : 'Failed';
        }

        return (string) $result;
    }

    /**
     * Helper to format a single Forge resource item.
     */
    protected function formatItem($item): string
    {
        if (property_exists($item, 'attributes')) {
            return json_encode($item->attributes, JSON_PRETTY_PRINT);
        }

        return json_encode($item, JSON_PRETTY_PRINT);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    /**
     * Get the fields required in the resource_mapping.
     */
    protected function requiredMappingFields(): array
    {
        return [
            'api_token' => 'Laravel Forge API Personal Access Token',
        ];
    }
}
