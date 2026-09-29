<?php

namespace Agentoom\Core\Ai\Tools\Public;

use Agentoom\Core\Attributes\GroupVisibility;
use Agentoom\Core\Attributes\Sentinelable;
use Agentoom\Core\Models\McpServer;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Services\McpService;
use Agentoom\Core\Traits\HandlesToolActions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

#[GroupVisibility('agentoom-core')]
class SearchResourcesTool implements Tool
{
    use HandlesToolActions;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Search for available resources and resource types to find necessary IDs and field mappings for other tools.
        The discovery process should be:
        1. Use "list_types" to see available resource types and their schemas (fields).
        2. Use "search" (with optional resource_type_id) to find specific resources by name, description or field values.
        Crucial: Always check the "Data Fields" in the search results to see what information is stored.';
    }

    /**
     * List all available resource types and their schemas.
     */
    #[Sentinelable(
        name: 'Search Resources Tool List Types',
        description: 'List available global resource types.',
        category: 'Resources'
    )]
    protected function listTypes(Request $request): string
    {
        $query = trim((string) $request->string('query'));
        $user = auth()->user();

        $types = ResourceType::visibleTo($user)->when($query !== '', function (Builder $q) use ($query) {
            $q->whereNameLike("%{$query}%")
                ->orWhere('description', 'like', "%{$query}%");
        })->get();

        if ($types->isEmpty()) {
            return 'No resource types found.'.($query ? " No match for '{$query}'." : '');
        }

        $output = "Available Resource Types:\n\n";

        foreach ($types as $type) {
            $output .= "### [Global] ID: global:{$type->id} - NAME: {$type->name_translated}\n";
            $output .= "Description: {$type->description}\n";
            $output .= "Custom Fields (stored in 'data' field of resources):\n";
            foreach ($type->fields as $field) {
                $required = ($field['required'] ?? false) ? 'required' : 'optional';
                $encrypted = ($field['encrypted'] ?? false) ? '(ENCRYPTED)' : '';
                $output .= "  - {$field['name']} ({$field['type']}, {$required}) {$encrypted}\n";
            }
            $output .= "---\n";
        }

        return $output;
    }

    /**
     * Search for available resources.
     */
    #[Sentinelable(
        name: 'Search Resources Tool Search',
        description: 'Search global resources by query and filters.',
        category: 'Resources'
    )]
    protected function search(Request $request): Stringable|string
    {
        $query = trim((string) $request->string('query'));
        $typeId = $request->integer('resource_type_id');
        $user = auth()->user();

        Log::debug('SearchResourcesTool handle', [
            'query' => $query,
            'resource_type_id' => $typeId,
            'user_id' => $user?->id,
        ]);

        if (! $user) {
            return 'Error: No user context provided for resource search.';
        }

        try {
            $resources = Resource::visibleTo($user)
                ->with(['resourceType'])
                ->when($query !== '', function (Builder $q) use ($query) {
                    $q->where(function ($sub) use ($query) {
                        $sub->where('name', 'like', "%{$query}%")
                            ->orWhere('description', 'like', "%{$query}%")
                            ->orWhere('data', 'like', "%{$query}%")
                            ->orWhereHas('resourceType', function ($typeQuery) use ($query) {
                                $typeQuery->whereNameLike("%{$query}%");
                            });
                    });
                })
                ->when($typeId > 0, function (Builder $q) use ($typeId) {
                    $q->where('resource_type_id', $typeId);
                })
                ->limit(20)
                ->get();

            // MCP Resources Discovery
            $mcpResources = [];
            if ($typeId <= 0) { // Only search MCP if no specific local type is filtered
                $servers = McpServer::where('status', 'active')->get();
                $mcpService = app(McpService::class);
                foreach ($servers as $server) {
                    try {
                        $result = $mcpService->listResources($server);
                        $serverResources = $result['resources'] ?? [];
                        foreach ($serverResources as $mcpRes) {
                            // Simple client-side filtering for demo purposes
                            if ($query === '' ||
                                str_contains(strtolower($mcpRes['name']), strtolower($query)) ||
                                str_contains(strtolower($mcpRes['description'] ?? ''), strtolower($query))) {
                                $mcpRes['server_name'] = $server->name;
                                $mcpRes['server_id'] = $server->id;
                                $mcpResources[] = $mcpRes;
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::error("SearchResourcesTool: Failed to fetch resources from MCP server [{$server->name}]: ".$e->getMessage(), [
                            'exception' => $e,
                            'server_id' => $server->id,
                        ]);
                    }
                }
            }

            if ($resources->isEmpty() && empty($mcpResources)) {
                Log::debug('SearchResourcesTool: No resources found', ['query' => $query, 'type_id' => $typeId]);

                $msg = 'No relevant resources found.';
                if ($typeId) {
                    $msg .= ' (filtered by type ID '.$typeId.').';
                }
                $msg .= "\nHint: Use \"list_types\" to see available resource types and their field definitions. You can also try a broader search query.";

                return $msg;
            }

            $output = 'Found '.($resources->count() + count($mcpResources))." resource(s):\n\n";

            foreach ($resources as $resource) {
                $output .= "### [Local] Resource: {$resource->name} ({$resource->resourceType->name_translated})\n";
                $output .= "ID: global:{$resource->id}\n";
                $output .= "Description: {$resource->description}\n";
                $output .= "Data Fields:\n";
                $data = $resource->data;
                $fields = [];
                foreach ($data as $key => $value) {
                    $fieldConfig = collect($resource->resourceType->fields)->firstWhere('name', $key);

                    // REDACT SENSITIVE FIELDS FOR THE AI
                    if ($fieldConfig && ! empty($fieldConfig['encrypted'])) {
                        $value = "[ENCRYPTED_REFERENCE:{$key}]";
                    } else {
                        $lowerKey = strtolower($key);
                        if (str_contains($lowerKey, 'password') || str_contains($lowerKey, 'ssh key') || str_contains($lowerKey, 'ssh_key') || str_contains($lowerKey, 'secret') || str_contains($lowerKey, 'token') || str_contains($lowerKey, 'key')) {
                            $value = '[REDACTED]';
                        } elseif (is_string($value) && str_starts_with($value, 'eyJpdiI6')) {
                            // Looks like a Laravel encrypted string
                            $value = '[REDACTED]';
                        }
                    }

                    if (is_array($value)) {
                        $value = json_encode($value);
                    }

                    $output .= "- {$key}: {$value}\n";
                    $fields[] = $key;
                }

                $output .= "\nUsage Example for other tools requiring this resource:\n";
                $firstField = $fields[0] ?? 'field_name';
                $output .= "Pass `resource_id=\"global:{$resource->id}\"` and optionally `resource_mapping={\"target_tool_field\": \"data.{$firstField}\"}` to use data from this resource.\n";
                $output .= "Note: Encrypted fields show as `[ENCRYPTED_REFERENCE:...]` - tools will automatically resolve these when you pass them in the mapping.\n";
                $output .= "\n---\n\n";
            }

            foreach ($mcpResources as $resource) {
                $output .= "### [Remote] Resource: {$resource['name']} (from MCP: {$resource['server_name']})\n";
                $output .= "URI: {$resource['uri']}\n";
                if (isset($resource['description'])) {
                    $output .= "Description: {$resource['description']}\n";
                }
                if (isset($resource['mimeType'])) {
                    $output .= "MIME Type: {$resource['mimeType']}\n";
                }
                $output .= "\nUsage: This is a remote resource. You can reference it using the URI in compatible tools.\n";
                $output .= "\n---\n\n";
            }

            Log::debug("SearchResourcesTool: User {$user->id} searched for: {$query} (type: {$typeId}) - Found ".($resources->count() + count($mcpResources)).' resources.');

            if (strlen($output) > 50000) {
                $output = substr($output, 0, 50000)."\n\n[Search results truncated due to length...]";
            }

            return $output;
        } catch (\Throwable $e) {
            Log::error('SearchResourcesTool error: '.$e->getMessage(), [
                'query' => $query,
                'exception' => $e,
            ]);

            return 'Error searching resources: '.$e->getMessage();
        }
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['search', 'list_types'])
                ->required()
                ->description('The action to perform: "search" to find resources, "list_types" to discover available resource types.'),

            'query' => $schema->string()
                ->description('Search query. For "search", it finds resources by name, description or data. For "list_types", it filters types by name or description.'),

            'resource_type_id' => $schema->integer()
                ->description('Optional: Filter the "search" by a specific resource type ID.'),
        ];
    }
}
