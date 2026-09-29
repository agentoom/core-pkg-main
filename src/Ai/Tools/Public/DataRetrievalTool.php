<?php

namespace Agentoom\Core\Ai\Tools\Public;

use Agentoom\Core\Attributes\GroupVisibility;
use Agentoom\Core\Attributes\Sentinelable;
use Agentoom\Core\Services\DataRetrievalService;
use Agentoom\Core\Traits\HandlesToolActions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

#[GroupVisibility('agentoom-core')]
class DataRetrievalTool implements Tool
{
    use HandlesToolActions;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Retrieve data from resources with advanced filtering and structure navigation.
        Allows navigating resource groups, types, and individual resources.
        Useful for automations that need to discover available data structures and fetch specific records.';
    }

    /**
     * Retrieve resource groups.
     */
    #[Sentinelable(
        name: 'Data Retrieval Tool Get Resource Groups',
        description: 'Retrieve available resource groups.',
        category: 'Data Retrieval'
    )]
    protected function getResourceGroups(Request $request): string
    {
        $data = app(DataRetrievalService::class)->getResourceGroups();

        return $this->formatOutput($data, 'Resource Groups');
    }

    /**
     * Retrieve resource types.
     */
    #[Sentinelable(
        name: 'Data Retrieval Tool Get Resource Types',
        description: 'Retrieve available resource types.',
        category: 'Data Retrieval'
    )]
    protected function getResourceTypes(Request $request): string
    {
        $groupId = $request->integer('resource_group_id');
        $data = app(DataRetrievalService::class)->getResourceTypes($groupId ?: null);

        return $this->formatOutput($data, 'Resource Types');
    }

    /**
     * Retrieve resources with filters.
     */
    #[Sentinelable(
        name: 'Data Retrieval Tool Get Resources',
        description: 'Retrieve resources of a specific type.',
        category: 'Data Retrieval'
    )]
    protected function getResources(Request $request): string
    {
        $limit = $request->integer('limit', 20);
        $filters = $request->collect('filters')->toArray();
        $typeId = $request->integer('resource_type_id');
        $fields = $request->collect('fields')->toArray();
        $countOnly = $request->boolean('count_only');

        $data = app(DataRetrievalService::class)->getResources($limit, $filters, $typeId ?: null, $fields, $countOnly);

        return $this->formatOutput($data, 'Resources');
    }

    /**
     * Format the retrieved data for the AI agent.
     */
    protected function formatOutput(array|int $data, string $label): string
    {
        if (is_int($data)) {
            return "Total {$label} found: {$data}";
        }

        if (empty($data)) {
            return "No {$label} found matching the criteria.";
        }

        $output = 'Found '.count($data)." {$label}:\n\n";
        foreach ($data as $item) {
            $output .= '### ID: '.($item['id'] ?? 'N/A')."\n";
            foreach ($item as $key => $value) {
                if (in_array($key, ['id'])) {
                    continue;
                }

                // Redact potentially sensitive data similar to SearchResourcesTool
                $lowerKey = strtolower($key);
                if (str_contains($lowerKey, 'password') || str_contains($lowerKey, 'secret') || str_contains($lowerKey, 'token') || str_contains($lowerKey, 'key')) {
                    $value = '[REDACTED]';
                }

                if (is_array($value)) {
                    $value = json_encode($value);
                }
                $output .= "- {$key}: {$value}\n";
            }
            $output .= "---\n";
        }

        if (strlen($output) > 50000) {
            $output = substr($output, 0, 50000)."\n\n[Results truncated due to length...]";
        }

        return $output;
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['get_resources', 'get_resource_groups', 'get_resource_types'])
                ->required()
                ->description('The action to perform: "get_resource_groups" to list groups, "get_resource_types" to list types (optionally filtered by group), and "get_resources" to retrieve resource records.'),

            'limit' => $schema->integer()
                ->description('Maximum number of records to retrieve (default: 20). Only applicable for "get_resources".'),

            'filters' => $schema->array()
                ->description('List of filter strings or nested arrays for grouping. Example: ["seo = \'S\'", ["date_last_analysis is empty", "OR date_last_analysis < \'7 days ago\'"]]. Operators: >, <, >=, <=, =, !=, contains, starts with, ends with, is empty, is null.'),

            'fields' => $schema->array()
                ->items($schema->string())
                ->description('Optional: List of fields to retrieve for "get_resources". If empty, all fields are retrieved.'),

            'count_only' => $schema->boolean()
                ->description('Optional: If true, returns only the total count of matching records instead of the data itself. Only for "get_resources".'),

            'resource_type_id' => $schema->integer()
                ->description('Optional: Filter resources by type ID. Only for "get_resources".'),

            'resource_group_id' => $schema->integer()
                ->description('Optional: Filter resource types by group ID. Only for "get_resource_types".'),
        ];
    }
}
