<?php

namespace Agentoom\Core\Ai\Tools;

use Agentoom\Core\Models\McpServer;
use Agentoom\Core\Services\McpService;
use Agentoom\Core\Traits\HandlesPrivacy;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

abstract class McpProxyTool implements Tool
{
    use HandlesPrivacy;

    protected int $serverId;

    protected string $remoteName;

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        // Enforce the forbidden terms guardrail before processing anything
        if ($forbiddenError = $this->checkForForbiddenSecurityTerms($request)) {
            return $forbiddenError;
        }

        $server = McpServer::find($this->serverId);

        if (! $server) {
            return "Error: MCP Server not found (ID: {$this->serverId})";
        }

        try {
            $mcpService = app(McpService::class);
            $arguments = $request->all();

            // Filter out internal explanation parameter
            unset($arguments['explanation']);

            // Deanonymize PII placeholders before sending to external MCP server
            $mappingKey = $this->resolvePrivacyMappingKey();
            if ($mappingKey !== null) {
                $arguments = $this->deanonymizeArguments($arguments, $mappingKey);
            }

            $result = $mcpService->callTool($server, $this->remoteName, $arguments);

            // MCP callTool result is usually an object with 'content' array.
            // Each content item has 'type' and 'text' (or other fields).

            if (isset($result['content']) && is_array($result['content'])) {
                $output = '';
                foreach ($result['content'] as $content) {
                    if ($content['type'] === 'text') {
                        $output .= $content['text']."\n";
                    }
                    // Handle other types if needed (e.g. image)
                }

                return $this->anonymizeResponse(trim($output), $mappingKey);
            }

            return $this->anonymizeResponse(json_encode($result, JSON_PRETTY_PRINT), $mappingKey);
        } catch (\Exception $e) {
            return "Error calling MCP tool '{$this->remoteName}': ".$e->getMessage();
        }
    }

    /**
     * This method is required by Tool contract. For MCP tools, the schema
     * is managed externally and stored in the database.
     */
    public function schema(JsonSchema $schema): array
    {
        $tool = \Agentoom\Core\Models\Tool::where('class_name', static::class)->first();

        if (! $tool || empty($tool->schema)) {
            return [];
        }

        $schemaData = $tool->schema;

        // If it's a root object schema, we want to return its properties.
        if (isset($schemaData['type']) && $schemaData['type'] === 'object' && isset($schemaData['properties'])) {
            return $this->mapProperties($schema, $schemaData['properties'], $schemaData['required'] ?? []);
        }

        // If it doesn't match the expected structure, but has properties at top level
        if (isset($schemaData['properties'])) {
            return $this->mapProperties($schema, $schemaData['properties'], $schemaData['required'] ?? []);
        }

        return [];
    }

    /**
     * Map a properties array to an array of Type objects.
     */
    protected function mapProperties(JsonSchema $builder, array $properties, array $required): array
    {
        $fields = [];
        foreach ($properties as $name => $prop) {
            $fields[$name] = $this->mapPropertyToType($builder, $name, $prop, in_array($name, $required));
        }

        return $fields;
    }

    /**
     * Map a single property definition to a Type object.
     */
    protected function mapPropertyToType(JsonSchema $builder, string $name, array $prop, bool $isRequired): Type
    {
        $type = $prop['type'] ?? 'string';

        $isNullable = false;
        if (is_array($type)) {
            if (in_array('null', $type)) {
                $isNullable = true;
            }
            $filteredTypes = array_values(array_filter($type, fn ($t) => $t !== 'null'));
            $type = $filteredTypes[0] ?? 'string';
        }

        $method = match ($type) {
            'integer' => 'integer',
            'number' => 'number',
            'boolean' => 'boolean',
            'array' => 'array',
            'object' => 'object',
            default => 'string',
        };

        /** @var Type $typeObj */
        if ($method === 'object') {
            $typeObj = $builder->object($this->mapProperties($builder, $prop['properties'] ?? [], $prop['required'] ?? []));
        } else {
            $typeObj = $builder->$method();
        }

        if ($method === 'array' && isset($prop['items'])) {
            $typeObj->items($this->mapPropertyToType($builder, 'item', $prop['items'], false));
        }

        if (isset($prop['description'])) {
            $typeObj->description($prop['description']);
        }

        if ($isRequired) {
            $typeObj->required();
        }

        if ($isNullable) {
            $typeObj->nullable();
        }

        if (isset($prop['enum']) && method_exists($typeObj, 'enum')) {
            $typeObj->enum($prop['enum']);
        }

        if (isset($prop['default']) && method_exists($typeObj, 'default')) {
            $typeObj->default($prop['default']);
        }

        return $typeObj;
    }

    /**
     * Get the tool's description.
     */
    public function description(): Stringable|string
    {
        return '';
    }
}
