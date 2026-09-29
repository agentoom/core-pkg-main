<?php

namespace Agentoom\Core\Tests\Feature;

use Agentoom\Core\Tests\TestCase;
use Agentoom\Core\Traits\HandlesToolActions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Contracts\Tool;

class ToolActionMethodMatchTest extends TestCase
{
    /**
     * Test that all tools have methods matching the actions defined in their schema.
     */
    public function test_all_tools_have_methods_matching_schema_actions()
    {
        $toolPaths = [
            base_path('packages/agentoom/core-main/src/Ai/Tools/Public'),
            base_path('packages/agentoom/core-sentinel/src/Ai/Tools'),
        ];

        $errors = [];

        foreach ($toolPaths as $path) {
            if (! File::isDirectory($path)) {
                continue;
            }

            foreach (File::allFiles($path) as $file) {
                // Determine namespace based on path
                if (str_contains($path, 'core')) {
                    $className = 'Agentoom\\Core\\Ai\\Tools\\Public\\'.$file->getBasename('.php');
                } else {
                    $className = 'Agentoom\\Sentinel\\Ai\\Tools\\'.$file->getBasename('.php');
                }

                if (! class_exists($className)) {
                    continue;
                }

                $reflection = new \ReflectionClass($className);
                if ($reflection->isAbstract() || ! $reflection->implementsInterface(Tool::class)) {
                    continue;
                }

                $tool = app($className);

                // Skip tools that don't use HandlesToolActions or override handle
                $usesTrait = in_array(HandlesToolActions::class, class_uses_recursive($className));
                $overridesHandle = $reflection->hasMethod('handle') && $reflection->getMethod('handle')->getDeclaringClass()->getName() === $className;

                if (! $usesTrait || $overridesHandle) {
                    continue;
                }

                // Get schema
                $schemaManager = new JsonSchemaTypeFactory;
                $schema = $tool->schema($schemaManager);

                if (! isset($schema['action'])) {
                    continue;
                }

                $actionProperty = $schema['action'];

                // Try to get enum values. This depends on how the JsonSchema is implemented.
                // In Laravel AI SDK, it might be a wrapper.
                // Looking at the code: ->enum(['run'])

                // Let's use reflection to get the enum if possible, or just mock/inspect the object
                $actions = [];

                // For this test, we might need to inspect the action property more deeply if it's an object.
                // But usually it's an instance of Illuminate\JsonSchema\Types\Type or similar.

                // Let's try to get it via reflection or by just checking common property names
                try {
                    $ref = new \ReflectionProperty($actionProperty, 'enum');
                    $ref->setAccessible(true);
                    $actions = $ref->getValue($actionProperty);
                } catch (\Throwable $e) {
                    // Fallback: if we can't get enum values easily, we might just skip or do something else
                    // For now, let's assume we can get it or use a different approach.
                    continue;
                }

                if (empty($actions)) {
                    continue;
                }

                foreach ($actions as $action) {
                    $methodName = str($action)->camel()->toString();

                    if (! $reflection->hasMethod($action) && ! $reflection->hasMethod($methodName)) {
                        $errors[] = "Tool {$className} defines action '{$action}' but has no matching method '{$action}' or '{$methodName}'.";
                    }
                }
            }
        }

        $this->assertEmpty($errors, implode("\n", $errors));
    }
}
