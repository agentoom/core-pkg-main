<?php

namespace Agentoom\Core\Services;

use Agentoom\Core\Attributes\GroupVisibility;
use Agentoom\Core\Contracts\PrivacyIntegration;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\AgentSkill;
use Agentoom\Core\Models\McpActionable;
use Agentoom\Core\Models\McpServer;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Models\Tool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool as ToolContract;
use ReflectionClass;

class McpDiscoveryService
{
    /**
     * Discover and register all tools.
     */
    public function discoverAndRegister(?\Closure $onProgress = null): array
    {
        try {
            if (! Schema::hasTable('tools')) {
                return [];
            }
        } catch (\Throwable $e) {
            return [];
        }

        // 1. Collect all tool class names and their origins to avoid duplicate work
        $toolClasses = [];

        // Filesystem tools
        foreach ($this->getFileSystemToolClasses(__DIR__.'/../Ai/Tools', 'Agentoom\\Core\\Ai\\Tools\\') as $className) {
            $toolClasses[$className] = ['isPublic' => true];
        }
        foreach ($this->getFileSystemToolClasses(app_path('Ai/Tools'), 'App\\Ai\\Tools\\') as $className) {
            $toolClasses[$className] = ['isPublic' => true];
        }

        // Public tools contributed by packages (discovered like the filesystem ones)
        foreach (app(ToolRegistry::class)->publicTools() as $className) {
            $toolClasses[$className] = ['isPublic' => true];
        }

        // Global extension tools
        $registeredTools = app(ToolRegistry::class)->all();
        foreach ($registeredTools as $className) {
            $toolClasses[$className] = ['isPublic' => true];
        }

        // Database tools (DB-backed tools already in the database)
        $dbTools = Tool::where('is_db_backed', true)->get()->keyBy('class_name');
        foreach ($dbTools as $className => $tool) {
            $toolClasses[$className] = ['isPublic' => true, 'existingTool' => $tool];
        }

        // Sync tools from active MCP servers
        $this->syncMcpServers();

        // Refresh database tools after MCP sync
        $dbTools = Tool::where('is_db_backed', true)->get()->keyBy('class_name');
        foreach ($dbTools as $className => $tool) {
            $toolClasses[$className] = ['isPublic' => true, 'existingTool' => $tool];
        }

        // Pre-load all existing tools to minimize queries
        $existingTools = Tool::whereIn('class_name', array_keys($toolClasses))->get()->keyBy('class_name');

        // 2. Register all tools in a single transaction for performance
        return DB::transaction(function () use ($toolClasses, $existingTools, $onProgress) {
            $discoveredTools = [];

            foreach ($toolClasses as $className => $info) {
                $this->registerTool(
                    $className,
                    $discoveredTools,
                    $info['existingTool'] ?? $existingTools->get($className),
                    $info['isPublic'] ?? null
                );

                if ($onProgress && ! empty($discoveredTools)) {
                    $onProgress(end($discoveredTools));
                }
            }

            // Remove tools from the database that don't exist anymore or are not public
            $this->removeMissingTools($discoveredTools);

            return $discoveredTools;
        });
    }

    /**
     * Get tool classes from the filesystem.
     */
    protected function getFileSystemToolClasses($basePath, $baseNamespace): array
    {
        $toolsPath = $basePath.'/Public';
        $namespace = $baseNamespace.'Public\\';

        if (! File::isDirectory($toolsPath)) {
            return [];
        }

        $files = File::allFiles($toolsPath);
        $classes = [];

        foreach ($files as $file) {
            $relativePath = $file->getRelativePathname();
            $relativeNamespace = str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $relativePath);
            $classes[] = $namespace.$relativeNamespace;
        }

        return $classes;
    }

    /**
     * Register a single tool.
     */
    protected function registerTool(string $className, array &$discoveredTools, ?Tool $existingTool = null, ?bool $isPublic = null): void
    {
        $tool = $existingTool;

        $baseData = [
            'name' => $this->generateToolName($className),
            'is_db_backed' => $tool->is_db_backed ?? false,
            'code' => $tool->code ?? null,
        ];

        if ($isPublic !== null) {
            $baseData['is_public'] = $isPublic;
        }

        try {
            // 2. Try to instantiate and extract registration details
            if ($baseData['is_db_backed']) {
                $toolInstance = $tool->instantiate();
            } else {
                if (! class_exists($className)) {
                    throw new \Exception("Class {$className} not found");
                }
                $toolInstance = app($className);
            }

            $reflection = new ReflectionClass($toolInstance);

            if ($reflection->isAbstract() || ! $reflection->implementsInterface(ToolContract::class)) {
                throw new \Exception("Class {$className} is not a valid tool");
            }

            // Detect #[GroupVisibility] attribute on the tool class
            $hasVisibilityColumn = Schema::hasColumn('tools', 'visibility_group');
            $visibilityGroup = null;

            if ($hasVisibilityColumn) {
                $groupVisibilityAttributes = $reflection->getAttributes(GroupVisibility::class);
                if (! empty($groupVisibilityAttributes)) {
                    $visibilityGroup = $groupVisibilityAttributes[0]->newInstance()->group;
                }
            }

            // Check if this tool's visibility group is active
            $isVisible = true;
            if ($hasVisibilityColumn) {
                $visibilityService = app(VisibilityService::class);
                $isVisible = $visibilityService->isGroupVisible($visibilityGroup);
            }

            $description = (string) $toolInstance->description();
            $schema = $this->extractSchema($toolInstance);

            // For DB-backed tools (like MCP), if extraction fails or returns empty,
            // we preserve the existing data which might have been synced from elsewhere.
            if ($baseData['is_db_backed']) {
                $description = $description ?: ($tool->description ?? '');
                $schema = ! empty($schema) ? $schema : ($tool->schema ?? []);
            }

            $updateData = array_merge($baseData, [
                'description' => $description,
                'schema' => $schema,
                'is_available' => $isVisible,
            ]);

            if ($hasVisibilityColumn) {
                $updateData['visibility_group'] = $visibilityGroup;
            }
        } catch (\Throwable $e) {
            logger()->error("Failed to register tool {$className}: {$e->getMessage()}");

            $updateData = array_merge($baseData, [
                'description' => $tool->description ?? 'No description available.',
                'is_available' => false,
            ]);
        }

        if ($tool) {
            // Invalidate actionable cache if visibility_group changed
            if (isset($updateData['visibility_group'])
                && $hasVisibilityColumn
                && $tool->visibility_group !== $updateData['visibility_group']) {
                Cache::forget('agentoom_actionable_methods');
            }
            $tool->update($updateData);
        } else {
            $tool = Tool::create(array_merge(['class_name' => $className], $updateData));
        }

        $discoveredTools[] = $tool;
    }

    /**
     * Remove tools from the database that don't exist anymore or are not public.
     */
    protected function removeMissingTools(array $discoveredTools): void
    {
        $discoveredIds = array_map(fn ($tool) => $tool->id, $discoveredTools);

        // Delete any tool that was not discovered OR is not public.
        // This ensures the database only contains public, current tools.
        $toolsToDelete = Tool::whereNotIn('id', $discoveredIds)
            ->orWhere('is_public', false)
            ->get();

        foreach ($toolsToDelete as $tool) {
            $tool->delete();
        }
    }

    /**
     * Extract the schema from a tool instance.
     */
    protected function extractSchema(ToolContract $tool): array
    {
        $mockTypeClass = new class
        {
            public array $data = [];

            public function string(): self
            {
                $this->data['type'] = 'string';

                return $this;
            }

            public function integer(): self
            {
                $this->data['type'] = 'integer';

                return $this;
            }

            public function boolean(): self
            {
                $this->data['type'] = 'boolean';

                return $this;
            }

            public function number(): self
            {
                $this->data['type'] = 'number';

                return $this;
            }

            public function array(): self
            {
                $this->data['type'] = 'array';

                return $this;
            }

            public function object(\Closure|array $properties = []): self
            {
                $this->data['type'] = 'object';
                if (is_array($properties)) {
                    $this->data['properties'] = array_map(fn ($t) => method_exists($t, 'toArray') ? $t->toArray() : (isset($t->data) ? $t->data : $t), $properties);
                }

                return $this;
            }

            public function required(): self
            {
                $this->data['required'] = true;

                return $this;
            }

            public function description(string $description): self
            {
                $this->data['description'] = $description;

                return $this;
            }

            public function enum(array $values): self
            {
                $this->data['enum'] = $values;

                return $this;
            }

            public function items(mixed $schema): self
            {
                $this->data['items'] = method_exists($schema, 'toArray') ? $schema->toArray() : (isset($schema->data) ? $schema->data : $schema);

                return $this;
            }

            public function toArray(): array
            {
                return $this->data;
            }
        };

        $mockSchema = new class($mockTypeClass) implements JsonSchema
        {
            public function __construct(protected $mockTypeClass) {}

            public function string()
            {
                return (clone $this->mockTypeClass)->string();
            }

            public function integer()
            {
                return (clone $this->mockTypeClass)->integer();
            }

            public function boolean()
            {
                return (clone $this->mockTypeClass)->boolean();
            }

            public function number()
            {
                return (clone $this->mockTypeClass)->number();
            }

            public function array()
            {
                return (clone $this->mockTypeClass)->array();
            }

            public function object(\Closure|array $properties = [])
            {
                return (clone $this->mockTypeClass)->object($properties);
            }

            public function union(array $types)
            {
                return (clone $this->mockTypeClass)->union($types);
            }

            public function anyOf(\Closure|array $schemas)
            {
                return (clone $this->mockTypeClass)->anyOf($schemas);
            }
        };

        try {
            $schema = $tool->schema($mockSchema);

            return array_map(fn ($t) => method_exists($t, 'toArray') ? $t->toArray() : (isset($t->data) ? $t->data : $t), $schema);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Generate a human-readable name from the class name.
     */
    protected function generateToolName(string $className): string
    {
        $shortName = class_basename($className);

        // Handle dynamically generated MCP tool names
        if (str_starts_with($shortName, 'McpTool_')) {
            $parts = explode('_', $shortName, 3);
            if (count($parts) === 3) {
                try {
                    if (Schema::hasTable('mcp_servers')) {
                        $server = McpServer::find($parts[1]);
                        $safeName = preg_replace('/_[0-9a-f]{8}$/', '', $parts[2]);
                        $remoteName = str_replace('_', '-', $safeName);

                        return ($server->name ?? 'MCP').": {$remoteName}";
                    }
                } catch (\Throwable $e) {
                    // Fall back to default naming if table is missing
                }
            }
        }

        return preg_replace('/(?<!^)[A-Z]/', ' $0', $shortName);
    }

    /**
     * Sync tools and resources from all active MCP servers.
     */
    public function syncMcpServers(): void
    {
        try {
            if (! Schema::hasTable('mcp_servers')) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }

        $servers = McpServer::where('status', 'active')->get();
        foreach ($servers as $server) {
            try {
                $this->syncServer($server);
            } catch (\Throwable $e) {
                Log::error("Failed to sync MCP server [{$server->name}]: ".$e->getMessage(), [
                    'exception' => $e,
                    'server_id' => $server->id,
                ]);
            }
        }
    }

    /**
     * Sync tools, resources, and elements from a single MCP server.
     *
     * Agents are imported LAST to ensure tools, skills, actionables, and all
     * other dependencies are available before assigning them to agents.
     */
    public function syncServer(McpServer $server, bool $overwrite = false): array
    {
        $toolsReport = $this->syncServerTools($server, $overwrite);
        $resourcesReport = $this->syncServerResources($server, $overwrite);
        $elementsReport = $this->syncServerElements($server, $overwrite);
        $actionablesReport = $this->syncServerActionables($server, $overwrite);
        $privacyReport = $this->syncServerPrivacyRecognizers($server);

        // Import agents LAST so all dependencies (tools, skills, actionables)
        // are already in the database when agent assignments are resolved.
        $agentsReport = $this->importAgents($server, $elementsReport['agents_data'], $overwrite);

        return [
            'tools' => $toolsReport,
            'resources' => $resourcesReport,
            'agents' => $agentsReport,
            'skills' => $elementsReport['skills'],
            'actionables' => $actionablesReport,
            'privacy_recognizers' => $privacyReport,
        ];
    }

    /**
     * Sync tools from a single MCP server.
     */
    public function syncServerTools(McpServer $server, bool $overwrite = false): array
    {
        $importedCount = 0;
        $existingCount = 0;
        $updatedCount = 0;
        $deletedCount = 0;
        $skipped = [];
        $discoveredIds = [];

        try {
            Log::info("Syncing tools for MCP server [{$server->name}] (ID: {$server->id})");
            $mcpService = app(McpService::class);
            $cursor = null;
            $totalReceived = 0;

            do {
                $result = $mcpService->listTools($server, $cursor);
                $tools = $result['tools'] ?? (isset($result[0]) ? $result : []);
                $pageCount = count($tools);
                $totalReceived += $pageCount;
                Log::info("Received page of tools for MCP server [{$server->name}]: {$pageCount} tools. Total so far: {$totalReceived}");

                $cursor = $result['nextCursor'] ?? null;

                foreach ($tools as $toolDef) {
                    $remoteName = $toolDef['name'];
                    // Sanitize remote name for class name
                    $safeRemoteName = preg_replace('/[^a-zA-Z0-9]/', '_', $remoteName);
                    // Add a short hash to avoid collisions if multiple tools sanitize to the same name
                    $hash = substr(md5($remoteName), 0, 8);
                    $className = "McpTool_{$server->id}_{$safeRemoteName}_{$hash}";
                    $fullClassName = "Agentoom\Core\Ai\Tools\Dynamic\\{$className}";

                    $code = "<?php\n\nnamespace Agentoom\Core\Ai\Tools\Dynamic;\n\n";
                    $code .= "class {$className} extends \Agentoom\Core\Ai\Tools\McpProxyTool {\n";
                    $code .= "    protected int \$serverId = {$server->id};\n";
                    $code .= "    protected string \$remoteName = '".addslashes($remoteName)."';\n";
                    $code .= "    public function description(): \Stringable|string { return '".addslashes($toolDef['description'] ?? '')."'; }\n";
                    $code .= '}';

                    $data = [
                        'name' => "{$server->name}: {$remoteName}",
                        'class_name' => $fullClassName,
                        'description' => $toolDef['description'] ?? '',
                        'schema' => $toolDef['inputSchema'] ?? [],
                        'is_available' => true,
                        'is_active' => true,
                        'is_public' => true,
                        'is_db_backed' => true,
                        'code' => $code,
                        'mcp_server_id' => $server->id,
                        'remote_name' => $remoteName,
                    ];

                    try {
                        $existingTool = Tool::where('class_name', $fullClassName)->first();

                        if ($existingTool) {
                            if ($overwrite) {
                                $existingTool->update($data);
                                $updatedCount++;
                            } else {
                                $existingCount++;
                            }
                            $discoveredIds[] = $existingTool->id;
                        } else {
                            $tool = Tool::create($data);
                            $discoveredIds[] = $tool->id;
                            $importedCount++;
                        }
                    } catch (UniqueConstraintViolationException $e) {
                        $skipped[] = "Tool: {$remoteName} (".__('agentoom-core::admin.mcp_servers.slug_conflict').')';
                        Log::warning("Skipped importing tool [{$remoteName}] for server [{$server->name}] due to unique constraint violation");
                    }
                }
            } while ($cursor);

            // Remove tools that are no longer on the server
            $toolsToDelete = Tool::where('mcp_server_id', $server->id)
                ->whereNotIn('id', $discoveredIds)
                ->get();

            $deletedCount = $toolsToDelete->count();

            foreach ($toolsToDelete as $tool) {
                $tool->delete();
            }

            $server->update([
                'last_synced_at' => now(),
                'status' => 'active',
                'error_message' => null,
            ]);

            return [
                'imported' => $importedCount,
                'existing' => $existingCount,
                'updated' => $updatedCount,
                'deleted' => $deletedCount,
                'skipped' => $skipped,
            ];
        } catch (\Throwable $e) {
            Log::error("Failed to sync tools for MCP server [{$server->name}]: ".$e->getMessage(), [
                'exception' => $e,
                'server_id' => $server->id,
            ]);
            throw $e;
        }
    }

    /**
     * Sync resources from a single MCP server.
     */
    public function syncServerResources(McpServer $server, bool $overwrite = false): array
    {
        $importedCount = 0;
        $existingCount = 0;
        $updatedCount = 0;
        $deletedCount = 0;
        $discoveredIds = [];

        try {
            $mcpService = app(McpService::class);
            $cursor = null;

            // Ensure MCP Resource Type exists
            $resourceType = ResourceType::firstOrCreate(
                ['name->en' => 'MCP Resource'],
                [
                    'name' => ['en' => 'MCP Resource'],
                    'description' => 'Resources provided by MCP servers.',
                    'config' => [
                        ['name' => 'uri', 'type' => 'text', 'required' => true],
                        ['name' => 'mimeType', 'type' => 'text'],
                    ],
                ]
            );

            $totalReceived = 0;
            do {
                $result = $mcpService->listResources($server, $cursor);
                $resources = $result['resources'] ?? (isset($result[0]) ? $result : []);
                $pageCount = count($resources);
                $totalReceived += $pageCount;
                Log::info("Received page of resources for MCP server [{$server->name}]: {$pageCount} resources. Total so far: {$totalReceived}");

                $cursor = $result['nextCursor'] ?? null;

                foreach ($resources as $resourceDef) {
                    if (! isset($resourceDef['uri'])) {
                        Log::warning("Skipping MCP resource without URI for server [{$server->name}]", ['resource' => $resourceDef]);

                        continue;
                    }

                    $uri = $resourceDef['uri'];

                    $existingResource = Resource::where('mcp_server_id', $server->id)
                        ->where('mcp_uri', $uri)
                        ->first();

                    $data = [
                        'name' => $resourceDef['name'] ?? $uri,
                        'description' => $resourceDef['description'] ?? '',
                        'resource_type_id' => $resourceType->id,
                        'mcp_server_id' => $server->id,
                        'project_id' => $server->project_id,
                        'mcp_uri' => $uri,
                        'data' => [
                            'uri' => $uri,
                            'mimeType' => $resourceDef['mimeType'] ?? null,
                        ],
                    ];

                    if ($existingResource) {
                        if ($overwrite) {
                            $data['project_id'] = $existingResource->project_id ?? $server->project_id;
                            $existingResource->update($data);
                            $updatedCount++;
                        } else {
                            $existingCount++;
                        }
                        $discoveredIds[] = $existingResource->id;
                    } else {
                        $resource = Resource::create($data);
                        $discoveredIds[] = $resource->id;
                        $importedCount++;
                    }
                }
            } while ($cursor);

            // Remove resources that are no longer on the server
            $deletedCount = Resource::where('mcp_server_id', $server->id)
                ->whereNotIn('id', $discoveredIds)
                ->delete();

            return [
                'imported' => $importedCount,
                'existing' => $existingCount,
                'updated' => $updatedCount,
                'deleted' => $deletedCount,
            ];
        } catch (\Throwable $e) {
            Log::error("Failed to sync resources for MCP server [{$server->name}]: ".$e->getMessage(), [
                'exception' => $e,
                'server_id' => $server->id,
            ]);
            throw $e;
        }
    }

    /**
     * Sync Agentoom skills from a single MCP server.
     *
     * Agents are NOT imported here — their import is deferred to the end of
     * {@see syncServer()} so that tools, actionables, and all other
     * dependencies are already present when agent assignments are resolved.
     */
    public function syncServerElements(McpServer $server, bool $overwrite = false): array
    {
        try {
            Log::info("Starting elements sync for MCP server [{$server->name}] (ID: {$server->id})");

            $mcpService = app(McpService::class);
            $elements = $mcpService->getAgentoomElements($server);

            if (empty($elements)) {
                return [
                    'agents_data' => null,
                    'skills' => ['imported' => 0, 'updated' => 0, 'existing' => 0, 'deleted' => 0, 'skipped' => []],
                ];
            }

            Log::info("Elements received from MCP server [{$server->name}]: ".json_encode($elements));

            // Only pass agents/skills if the response actually contained those keys.
            // If the MCP returns a different format (e.g. "elements" wrapper),
            // we pass null to signal "no data" and avoid accidental deletion.
            $hasAgentsKey = array_key_exists('agents', $elements);
            $hasSkillsKey = array_key_exists('skills', $elements);

            $skillsReport = $hasSkillsKey
                ? $this->importSkills($server, $elements['skills'] ?? [], $overwrite)
                : ['imported' => 0, 'updated' => 0, 'existing' => 0, 'deleted' => 0, 'skipped' => []];

            Log::info("Finished elements sync for MCP server [{$server->name}]");

            return [
                'agents_data' => $hasAgentsKey ? ($elements['agents'] ?? []) : null,
                'skills' => $skillsReport,
            ];
        } catch (\Throwable $e) {
            Log::error("Failed to sync elements for MCP server [{$server->name}]: ".$e->getMessage(), [
                'exception' => $e,
                'server_id' => $server->id,
            ]);
            throw $e;
        }
    }

    /**
     * Sync Actionables from a single MCP server.
     */
    public function syncServerActionables(McpServer $server, bool $overwrite = false): array
    {
        try {
            Log::info("Starting actionables sync for MCP server [{$server->name}] (ID: {$server->id})");

            $mcpService = app(McpService::class);
            $actionables = $mcpService->getActionables($server);

            Log::info("Actionables received from MCP server [{$server->name}]: ".count($actionables));

            $importedCount = 0;
            $updatedCount = 0;
            $existingCount = 0;
            $discoveredIds = [];

            foreach ($actionables as $actionableData) {
                // Validate visibility_group from remote MCP server
                $visibilityGroup = $actionableData['visibility_group'] ?? null;
                if ($visibilityGroup !== null && ! preg_match('/^[a-z0-9_]+$/i', (string) $visibilityGroup)) {
                    Log::warning("Skipping invalid visibility_group from MCP server [{$server->name}]: {$visibilityGroup}");
                    $visibilityGroup = null;
                }

                $data = [
                    'mcp_server_id' => $server->id,
                    'name' => $actionableData['name'],
                    'description' => $actionableData['description'] ?? null,
                    'category' => $actionableData['category'] ?? null,
                    'visibility_group' => $visibilityGroup,
                    'remote_class' => $actionableData['class'],
                    'remote_method' => $actionableData['method'],
                    'parameters_schema' => $actionableData['parameters'] ?? [],
                    'remote_base_url' => $server->url,
                ];

                $existing = McpActionable::where('mcp_server_id', $server->id)
                    ->where('remote_class', $actionableData['class'])
                    ->where('remote_method', $actionableData['method'])
                    ->first();

                if ($existing) {
                    if ($overwrite) {
                        $existing->update($data);
                        $updatedCount++;
                    } else {
                        $existingCount++;
                    }
                    $discoveredIds[] = $existing->id;
                } else {
                    $new = McpActionable::create($data);
                    $discoveredIds[] = $new->id;
                    $importedCount++;
                }
            }

            // Remove actionables no longer on the server
            $deletedCount = McpActionable::where('mcp_server_id', $server->id)
                ->whereNotIn('id', $discoveredIds)
                ->delete();

            Log::info("Finished actionables sync for MCP server [{$server->name}]");

            return [
                'imported' => $importedCount,
                'updated' => $updatedCount,
                'existing' => $existingCount,
                'deleted' => $deletedCount,
            ];
        } catch (\Throwable $e) {
            Log::error("Failed to sync actionables for MCP server [{$server->name}]: ".$e->getMessage(), [
                'exception' => $e,
                'server_id' => $server->id,
            ]);
            throw $e;
        }
    }

    /**
     * Import agents from MCP server.
     */
    protected function importAgents(McpServer $server, ?array $agents, bool $overwrite = false): array
    {
        // Null means the MCP server didn't return an 'agents' key at all
        // (e.g. unrecognised response format). Treat as "no data" rather than
        // "zero agents" to avoid accidentally deleting existing agents.
        if ($agents === null) {
            Log::info("No agents data received for MCP server [{$server->name}] — skipping agent import/deletion");

            return [
                'imported' => 0,
                'existing' => 0,
                'updated' => 0,
                'deleted' => 0,
                'skipped' => [],
            ];
        }

        Log::info('Importing '.count($agents)." agents for MCP server [{$server->name}]");
        $importedCount = 0;
        $existingCount = 0;
        $updatedCount = 0;
        $discoveredAgentIds = [];
        $skipped = [];

        foreach ($agents as $agentData) {
            $remoteName = $agentData['name'];
            Log::debug("Processing agent [{$remoteName}] for server [{$server->name}]");

            $data = [
                'name' => $agentData['name'],
                'description' => $agentData['description'] ?? '',
                'instructions' => $agentData['instructions'] ?? '',
                'role' => $agentData['role'] ?? null,
                'objective' => $agentData['objective'] ?? null,
                'is_active' => true,
                'is_public' => true,
                'mcp_server_id' => $server->id,
                'remote_name' => $remoteName,
                'project_id' => $server->project_id,
                'prioritize' => $agentData['prioritize'] ?? [],
                'constraints' => $agentData['constraints'] ?? [],
                'conditional_rules' => $agentData['conditional_rules'] ?? [],
                'output_format' => $agentData['output_format'] ?? [],
                'examples' => $agentData['examples'] ?? [],
                'operational_protocol' => $agentData['operational_protocol'] ?? null,
                'error_handling' => $agentData['error_handling'] ?? null,
                'is_cautious_constraint_enabled' => $agentData['is_cautious_constraint_enabled'] ?? true,
            ];

            if (isset($agentData['config'])) {
                $data['config'] = $agentData['config'];
            }

            try {
                $existingAgent = Agent::where('mcp_server_id', $server->id)
                    ->where('remote_name', $remoteName)
                    ->first();

                if ($existingAgent) {
                    $agent = $existingAgent;
                    if ($overwrite) {
                        $agent->update($data);
                        $updatedCount++;
                    } else {
                        // Assign project_id if the agent doesn't have one yet
                        if (empty($agent->project_id) && ! empty($server->project_id)) {
                            $agent->update(['project_id' => $server->project_id]);
                        }
                        $existingCount++;
                    }
                    $discoveredAgentIds[] = $agent->id;
                } else {
                    $agent = Agent::create($data);
                    $discoveredAgentIds[] = $agent->id;
                    $importedCount++;
                }

                // Sync tools
                if (isset($agentData['tools']) && is_array($agentData['tools'])) {
                    $toolNames = array_values(array_filter(array_map('trim', $agentData['tools'])));

                    $availableTools = Tool::where('mcp_server_id', $server->id)
                        ->orWhereNull('mcp_server_id')
                        ->get(['id', 'remote_name', 'class_name']);

                    $toolIndex = [];
                    foreach ($availableTools as $tool) {
                        $normalizedRemote = $this->normalizeName((string) $tool->remote_name);
                        if ($normalizedRemote !== '') {
                            $toolIndex[$normalizedRemote] = $tool->id;
                        }

                        $normalizedClass = $this->normalizeName((string) $tool->class_name);
                        if ($normalizedClass !== '') {
                            $toolIndex[$normalizedClass] = $tool->id;
                        }

                        $normalizedBasename = $this->normalizeName(class_basename((string) ($tool->class_name ?? $tool->remote_name)));
                        if ($normalizedBasename !== '') {
                            $toolIndex[$normalizedBasename] = $tool->id;
                        }
                    }

                    $toolIds = [];
                    $missingTools = [];
                    foreach ($toolNames as $toolName) {
                        $normalizedCandidates = array_unique([
                            $this->normalizeName($toolName),
                            $this->normalizeName(class_basename($toolName)),
                        ]);

                        $resolvedId = null;
                        foreach ($normalizedCandidates as $candidate) {
                            if ($candidate !== '' && isset($toolIndex[$candidate])) {
                                $resolvedId = $toolIndex[$candidate];
                                break;
                            }
                        }

                        if ($resolvedId !== null) {
                            $toolIds[] = $resolvedId;
                        } else {
                            $missingTools[] = $toolName;
                        }
                    }

                    $toolIds = array_values(array_unique($toolIds));

                    if (! empty($missingTools)) {
                        Log::warning("Some tools for agent [{$remoteName}] were not found on server [{$server->name}]: ".implode(', ', $missingTools));
                    }

                    Log::debug('Syncing '.count($toolIds)." tools for agent [{$remoteName}]");
                    $agent->tools()->sync($toolIds);
                }

                // Sync skills
                if (isset($agentData['skills']) && is_array($agentData['skills'])) {
                    $skillNames = array_values(array_filter(array_map('trim', $agentData['skills'])));

                    $availableSkills = AgentSkill::where('mcp_server_id', $server->id)
                        ->orWhereNull('mcp_server_id')
                        ->get(['id', 'remote_name', 'slug']);

                    $skillIndex = [];
                    foreach ($availableSkills as $skill) {
                        $normalizedRemote = $this->normalizeName((string) $skill->remote_name);
                        if ($normalizedRemote !== '') {
                            $skillIndex[$normalizedRemote] = $skill->id;
                        }

                        $normalizedBasename = $this->normalizeName(class_basename((string) $skill->remote_name));
                        if ($normalizedBasename !== '') {
                            $skillIndex[$normalizedBasename] = $skill->id;
                        }

                        $normalizedSlug = $this->normalizeName((string) $skill->slug);
                        if ($normalizedSlug !== '') {
                            $skillIndex[$normalizedSlug] = $skill->id;
                        }
                    }

                    $skillIds = [];
                    $missingSkills = [];
                    foreach ($skillNames as $skillName) {
                        $normalizedCandidates = array_unique([
                            $this->normalizeName($skillName),
                            $this->normalizeName(class_basename($skillName)),
                            $this->normalizeName($server->id.'-'.$skillName),
                        ]);

                        $resolvedId = null;
                        foreach ($normalizedCandidates as $candidate) {
                            if ($candidate !== '' && isset($skillIndex[$candidate])) {
                                $resolvedId = $skillIndex[$candidate];
                                break;
                            }
                        }

                        if ($resolvedId !== null) {
                            $skillIds[] = $resolvedId;
                        } else {
                            $missingSkills[] = $skillName;
                        }
                    }

                    $skillIds = array_values(array_unique($skillIds));

                    if (! empty($missingSkills)) {
                        Log::warning("Some skills for agent [{$remoteName}] were not found on server [{$server->name}]: ".implode(', ', $missingSkills));
                    }

                    Log::debug('Syncing '.count($skillIds)." skills for agent [{$remoteName}]");
                    $agent->skills()->sync($skillIds);
                }
            } catch (UniqueConstraintViolationException $e) {
                $skipped[] = "Agent: {$remoteName} (".__('agentoom-core::admin.mcp_servers.slug_conflict').')';
                Log::warning("Skipped importing agent [{$remoteName}] for server [{$server->name}] due to unique constraint violation (likely slug conflict)");
            }
        }

        $agentsToDelete = Agent::where('mcp_server_id', $server->id)
            ->whereNotIn('id', $discoveredAgentIds)
            ->get();

        $deletedCount = $agentsToDelete->count();

        foreach ($agentsToDelete as $agent) {
            $agent->delete();
        }

        if ($deletedCount > 0) {
            Log::info("Removed {$deletedCount} agents that are no longer present on server [{$server->name}]");
        }

        return [
            'imported' => $importedCount,
            'existing' => $existingCount,
            'updated' => $updatedCount,
            'deleted' => $deletedCount,
            'skipped' => $skipped,
        ];
    }

    /**
     * Import skills from MCP server.
     */
    protected function importSkills(McpServer $server, ?array $skills, bool $overwrite = false): array
    {
        if ($skills === null) {
            Log::info("No skills data received for MCP server [{$server->name}] — skipping skill import/deletion");

            return [
                'imported' => 0,
                'existing' => 0,
                'updated' => 0,
                'deleted' => 0,
                'skipped' => [],
            ];
        }

        Log::info('Importing '.count($skills)." skills for MCP server [{$server->name}]");
        $importedCount = 0;
        $existingCount = 0;
        $updatedCount = 0;
        $discoveredSkillIds = [];
        $skipped = [];

        foreach ($skills as $skillData) {
            $remoteName = $skillData['name'];
            Log::debug("Processing skill [{$remoteName}] for server [{$server->name}]");

            $instructions = $skillData['instructions'] ?? '';
            $name = $skillData['name'];
            $description = $skillData['description'] ?? '';
            $slug = $skillData['slug'] ?? Str::slug($name);

            if (preg_match('/^---\n(.*?)\n---\n?(.*)$/s', $instructions, $matches)) {
                $frontmatter = $matches[1];
                $instructions = trim($matches[2]);

                if (preg_match('/^name:\s*(.*)$/m', $frontmatter, $nameMatch)) {
                    $name = trim($nameMatch[1]);
                }
                if (preg_match('/^description:\s*(.*)$/m', $frontmatter, $descMatch)) {
                    $description = trim($descMatch[1]);
                }
                if (preg_match('/^slug:\s*(.*)$/m', $frontmatter, $slugMatch)) {
                    $slug = trim($slugMatch[1]);
                }
            }

            $prefixedSlug = $server->id.'-'.$slug;

            $skillTools = $skillData['tools'] ?? [];
            $mappedTools = [];

            $availableTools = Tool::where('mcp_server_id', $server->id)
                ->orWhereNull('mcp_server_id')
                ->get(['remote_name', 'class_name']);

            $toolClassByNormalizedName = [];
            foreach ($availableTools as $tool) {
                $normalizedRemote = $this->normalizeName((string) $tool->remote_name);
                if ($normalizedRemote !== '') {
                    $toolClassByNormalizedName[$normalizedRemote] = $tool->class_name;
                }

                $normalizedClass = $this->normalizeName((string) $tool->class_name);
                if ($normalizedClass !== '') {
                    $toolClassByNormalizedName[$normalizedClass] = $tool->class_name;
                }

                $normalizedBasename = $this->normalizeName(class_basename((string) ($tool->class_name ?? $tool->remote_name)));
                if ($normalizedBasename !== '') {
                    $toolClassByNormalizedName[$normalizedBasename] = $tool->class_name;
                }
            }

            foreach ($skillTools as $toolRef) {
                $ref = trim($toolRef);
                $normalizedCandidates = array_unique([
                    $this->normalizeName($ref),
                    $this->normalizeName(class_basename($ref)),
                ]);

                $resolvedClass = null;
                foreach ($normalizedCandidates as $candidate) {
                    if ($candidate !== '' && isset($toolClassByNormalizedName[$candidate])) {
                        $resolvedClass = $toolClassByNormalizedName[$candidate];
                        break;
                    }
                }

                if ($resolvedClass !== null) {
                    $mappedTools[] = $resolvedClass;
                } elseif ($ref !== '') {
                    $mappedTools[] = $ref;
                }
            }

            $data = [
                'name' => $name,
                'slug' => $prefixedSlug,
                'description' => $description,
                'instructions' => $instructions,
                'musts' => $skillData['musts'] ?? [],
                'donts' => $skillData['donts'] ?? [],
                'if_this_then' => $skillData['if_this_then'] ?? [],
                'tools' => $mappedTools,
                'is_active' => true,
                'mcp_server_id' => $server->id,
                'remote_name' => $remoteName,
            ];

            try {
                $existingSkill = AgentSkill::where('mcp_server_id', $server->id)
                    ->where('remote_name', $remoteName)
                    ->first();

                if ($existingSkill) {
                    if ($overwrite) {
                        $existingSkill->update($data);
                        $updatedCount++;
                    } else {
                        $existingCount++;
                    }
                    $discoveredSkillIds[] = $existingSkill->id;
                } else {
                    $skill = AgentSkill::create($data);
                    $discoveredSkillIds[] = $skill->id;
                    $importedCount++;
                }
            } catch (UniqueConstraintViolationException $e) {
                $skipped[] = "Skill: {$remoteName} (".__('agentoom-core::admin.mcp_servers.slug_conflict').')';
                Log::warning("Skipped importing skill [{$remoteName}] for server [{$server->name}] due to unique constraint violation (likely slug conflict)");
            }
        }

        $skillsToDelete = AgentSkill::where('mcp_server_id', $server->id)
            ->whereNotIn('id', $discoveredSkillIds)
            ->get();

        $deletedCount = $skillsToDelete->count();

        foreach ($skillsToDelete as $skill) {
            $skill->delete();
        }

        if ($deletedCount > 0) {
            Log::info("Removed {$deletedCount} skills that are no longer present on server [{$server->name}]");
        }

        return [
            'imported' => $importedCount,
            'existing' => $existingCount,
            'updated' => $updatedCount,
            'deleted' => $deletedCount,
            'skipped' => $skipped,
        ];
    }

    /**
     * Sync privacy rules from a single MCP server and rebuild the merged JSON file.
     *
     * @return array{imported: int, updated: int, existing: int}
     */
    public function syncServerPrivacyRecognizers(McpServer $server): array
    {
        return app(PrivacyIntegration::class)->syncServerPrivacyRecognizers($server);
    }

    private function normalizeName(string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($name));
    }
}
