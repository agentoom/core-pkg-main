<?php

namespace Agentoom\Core\Services;

use Agentoom\Core\Attributes\Actionable;
use Agentoom\Core\Attributes\GroupVisibility;
use Agentoom\Core\Models\McpActionable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Finder\Finder;

class ActionDiscoveryService
{
    /**
     * Classes to exclude from discovery (e.g., test/demo services not meant for production use).
     */
    protected array $skipClasses = [
        TestActionableService::class,
    ];

    /**
     * Discover all classes and methods marked with the #[Actionable] attribute.
     *
     * @return array<string, array{class: string, method: string, name: string, description: string, parameters: array<string, string>}>
     */
    public function discover(): array
    {
        return Cache::remember('agentoom_actionable_methods', 3600, function () {
            $actionables = $this->discoverInPath(realpath(__DIR__.'/../'), 'Agentoom\\Core\\');
            $actionables = array_merge($actionables, $this->discoverInPath(app_path(), 'App\\'));

            // Add MCP Actionables
            $visibilityService = app(VisibilityService::class);
            $mcpActionables = McpActionable::with('mcpServer')->get();
            foreach ($mcpActionables as $mcpActionable) {
                // Skip actionables that belong to an inactive visibility group
                if (! $visibilityService->isGroupVisible($mcpActionable->visibility_group)) {
                    continue;
                }

                $key = "mcp:{$mcpActionable->id}";
                $actionables[$key] = [
                    'class' => 'Agentoom\\Core\\Models\\McpActionable',
                    'method' => 'execute',
                    'display_class' => 'McpActionable',
                    'display_method' => $mcpActionable->name,
                    'name' => "[{$mcpActionable->mcpServer->name}] {$mcpActionable->name}",
                    'description' => $mcpActionable->description,
                    'category' => $mcpActionable->category ?: 'Remote Actions',
                    'parameters' => $this->normalizeMcpParameters($mcpActionable->parameters_schema ?? []),
                    'mcp_actionable_id' => $mcpActionable->id,
                    'source' => 'mcp',
                    'mcp_server_name' => $mcpActionable->mcpServer->name,
                    'ai_tool' => (bool) $mcpActionable->ai_tool,
                ];
            }

            return $actionables;
        });
    }

    /**
     * Convert MCP parameters_schema (list format) to associative format used by the UI.
     *
     * @param  array<int, array{name: string, type: string, required?: bool, default?: mixed}>  $schema
     * @return array<string, array{type: string, description: string}>
     */
    private function normalizeMcpParameters(array $schema): array
    {
        $parameters = [];
        foreach ($schema as $param) {
            if (! isset($param['name'])) {
                continue;
            }
            $rawType = $param['type'] ?? 'any';
            // Strip nullable prefix (e.g. "?string" → "string")
            $rawType = ltrim($rawType, '?');
            // Take first type if union (e.g. "array|string" → "array")
            if (str_contains($rawType, '|')) {
                $rawType = explode('|', $rawType)[0];
            }
            $parameters[$param['name']] = [
                'type' => $this->normalizePhpType($rawType),
                'description' => $param['description'] ?? '',
            ];
        }

        return $parameters;
    }

    private function normalizePhpType(string $type): string
    {
        return match ($type) {
            'int' => 'integer',
            'bool' => 'boolean',
            'float', 'double' => 'float',
            'string', 'integer', 'boolean', 'array' => $type,
            default => 'any',
        };
    }

    private function cleanMcpClassName(string $className): string
    {
        if (str_contains($className, 'Agentoom\\Core\\Ai\\Tools\\Dynamic\\McpTool_')) {
            $parts = explode('\\', $className);
            $shortName = end($parts);
            // McpTool_{serverId}_{safeName}_{hash}
            if (preg_match('/^McpTool_\d+_(.+)_[\da-f]{8}$/', $shortName, $matches)) {
                $name = $matches[1];
                if (str_ends_with($name, '_tool')) {
                    $name = substr($name, 0, -5);
                }

                return $name;
            }
        }

        $className = str_contains($className, '\\') ? substr($className, strrpos($className, '\\') + 1) : $className;

        if (str_ends_with($className, 'Tool')) {
            $className = substr($className, 0, -4);
        }

        return $className;
    }

    protected function discoverInPath(string $path, string $namespace): array
    {
        if (! $path || ! is_dir($path)) {
            return [];
        }

        $finder = new Finder;
        $finder->files()->in($path)->name('*.php')->exclude(['tests', 'Tests', 'test', 'Test']);

        $actionables = [];
        $visibilityService = app(VisibilityService::class);

        foreach ($finder as $file) {
            $class = $this->extractClassName($file->getRealPath(), realpath($path), $namespace);
            //            Log::debug("ActionDiscovery found class: $class for path: ".$file->getRealPath());

            if (! $class || ! class_exists($class)) {
                continue;
            }

            // Skip explicitly excluded classes
            if (in_array($class, $this->skipClasses, true)) {
                continue;
            }

            $reflectionClass = new ReflectionClass($class);
            $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);

            foreach ($methods as $method) {
                $attributes = $method->getAttributes(Actionable::class);

                foreach ($attributes as $attribute) {
                    $actionable = $attribute->newInstance();
                    $methodName = $method->getName();
                    $key = "{$class}@{$methodName}";

                    // Check #[GroupVisibility] on the method, then fall back to class-level attribute
                    $groupAttributes = $method->getAttributes(GroupVisibility::class);
                    if (empty($groupAttributes)) {
                        $groupAttributes = $reflectionClass->getAttributes(GroupVisibility::class);
                    }

                    if (! empty($groupAttributes)) {
                        $visibilityGroup = $groupAttributes[0]->newInstance()->group;
                        if (! $visibilityService->isGroupVisible($visibilityGroup)) {
                            continue;
                        }
                    }

                    $docComment = $method->getDocComment() ?: '';
                    $paramDescriptions = [];
                    if ($docComment) {
                        preg_match_all('/@param\s+\S+\s+\$(\w+)\s+(.+)/', $docComment, $matches);
                        if (! empty($matches[1])) {
                            foreach ($matches[1] as $index => $paramName) {
                                $paramDescriptions[$paramName] = trim($matches[2][$index]);
                            }
                        }
                    }

                    $parameters = [];
                    foreach ($method->getParameters() as $parameter) {
                        $paramName = $parameter->getName();
                        $rawType = $parameter->getType() ? (string) $parameter->getType() : 'any';
                        $parameters[$paramName] = [
                            'type' => $this->normalizePhpType($rawType),
                            'description' => $paramDescriptions[$paramName] ?? '',
                        ];
                    }

                    $actionables[$key] = [
                        'class' => $class,
                        'method' => $methodName,
                        'display_class' => $this->cleanMcpClassName($class),
                        'display_method' => $methodName,
                        'name' => $actionable->name ?: $methodName,
                        'description' => $actionable->description,
                        'category' => $actionable->category,
                        'parameters' => $parameters,
                        'source' => 'local',
                    ];
                }
            }
        }

        return $actionables;
    }

    /**
     * Extract full class name from file path.
     */
    private function extractClassName(string $filePath, string $basePath, string $baseNamespace): ?string
    {
        $basePath = realpath($basePath);
        $path = str_replace($basePath, '', $filePath);
        $path = str_replace('.php', '', $path);
        $path = trim($path, DIRECTORY_SEPARATOR);

        return $baseNamespace.str_replace(DIRECTORY_SEPARATOR, '\\', $path);
    }
}
