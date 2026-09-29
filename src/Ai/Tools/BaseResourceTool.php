<?php

namespace Agentoom\Core\Ai\Tools;

use Agentoom\Core\Models\Resource;
use Agentoom\Core\Traits\HandlesToolActions;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

abstract class BaseResourceTool implements Tool
{
    use HandlesToolActions;

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

        // 1. Explicit global resource
        if (str_starts_with($resourceId, 'global:')) {
            $id = substr($resourceId, 7);

            return Resource::visibleTo($user)
                ->with(['resourceType', 'user'])
                ->find($id);
        }

        // 2. Fallback: try global resource
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

            // Check if it's a literal reference
            if (preg_match('/\[ENCRYPTED_REFERENCE:([a-zA-Z0-9_\-]+)\]/', $sourcePath, $matches)) {
                $key = $matches[1];
                $value = $resource->getDecryptedData()[$key] ?? null;
            } else {
                // Try standard paths first
                $value = data_get($context, $sourcePath);

                // Fallback: if they omitted 'data.' prefix, try adding it
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

        // Fallback to what Request->array() would have done
        if (! is_array($params)) {
            $params = $request->array($key);
        }

        // Case 1: Array with one stringified JSON element (common LLM mistake)
        if (count($params) === 1 && isset($params[0]) && is_string($params[0])) {
            $decoded = json_decode($params[0], true);
            if (is_array($decoded)) {
                $params = $decoded;
            }
        }

        // Case 2: List of objects (common LLM mistake when sending associative arrays)
        // We only merge if the array seems to be a list of objects and mergeObjects is true
        if ($mergeObjects && array_is_list($params) && count($params) > 0 && is_array($params[0])) {
            $merged = [];
            foreach ($params as $p) {
                if (is_array($p)) {
                    $merged = array_merge($merged, $p);
                }
            }

            $params = $merged;
        }

        // AUTO RESOLVE ENCRYPTED REFERENCES
        // If a resource_id is provided, check if any of the params contain [ENCRYPTED_REFERENCE:...]
        // We skip this for resource_mapping as it handles its own resolution logic
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
     * Validate that all required fields are present in the mapped data.
     */
    protected function validateMappedData(array $mapped, Resource $resource): ?string
    {
        $missingFields = [];
        foreach ($this->requiredMappingFields() as $field => $description) {
            $val = $mapped[$field] ?? null;
            if ($val === null || trim(is_array($val) ? json_encode($val) : (string) $val) === '') {
                $missingFields[] = "{$field} ({$description})";
            }
        }

        if (! empty($missingFields)) {
            $fieldsList = implode(', ', $missingFields);

            return "Error: The resource '{$resource->name}' (ID: {$resource->id}) is missing required information for this tool: {$fieldsList}. Please check if the resource has these fields or if your resource_mapping is correct.";
        }

        return null;
    }

    /**
     * Get the fields required in the resource_mapping.
     *
     * @return array<string, string> Key is the field name, value is a description.
     */
    abstract protected function requiredMappingFields(): array;
}
