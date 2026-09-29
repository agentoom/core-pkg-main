<?php

namespace Agentoom\Core\Ai\Tools\Public;

use Agentoom\Core\Attributes\GroupVisibility;
use Agentoom\Core\Attributes\Sentinelable;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Traits\HandlesToolActions;
use Agentoom\Core\Traits\NormalizesIds;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

#[GroupVisibility('agentoom-core')]
class ManageResourcesTool implements Tool
{
    use HandlesToolActions;
    use NormalizesIds;

    public function description(): Stringable|string
    {
        return 'Store and manage Global Resources (IDs are prefixed with "global:").
        Use this tool to create new resource types and resources within them when you find something useful to store for later retrieval at a system-wide level.
        CRITICAL: This tool is EXCLUSIVE to Global Resources. For project-specific resources, use "ProjectRepositoryTool" instead.
        CRITICAL: For finding or discovering existing resources or types, use "SearchResourcesTool" instead.
        CRITICAL: The data parameter needs to be provided as a proper JSON object.
        When creating a resource, use ONLY the custom fields defined in the resource type config. If you find extra information, add it to the "description" field instead.
        Before creating a new resource type or a new resource, you MUST explain the need to the user and ask for permission.';
    }

    /**
     * List all available resource types and their schemas.
     */
    #[Sentinelable(
        name: 'Manage Resources Tool List Types',
        description: 'List all available global resource types.',
        category: 'Resources'
    )]
    protected function listTypes(): string
    {
        $types = ResourceType::visibleTo(auth()->user())->get();
        if ($types->isEmpty()) {
            return 'No global resource types found. You can create a new one using the "create_type" action.';
        }

        $output = "Available Resource Types:\n\n";
        foreach ($types as $type) {
            $output .= "### ID - NAME: {$type->id} - {$type->name_translated}\n";
            $output .= "Description: {$type->description}\n";
            $output .= "Standard Fields (always available for any resource of this type):\n";
            $output .= "  - name (text, required)\n";
            $output .= "  - description (textarea, optional)\n";
            $output .= "Custom Fields (defined for this type, provide in the 'data' parameter):\n";
            foreach ($type->fields as $field) {
                $required = ($field['required'] ?? false) ? 'required' : 'optional';
                $output .= "  - {$field['name']} ({$field['type']}, {$required})\n";
            }
            $output .= "---\n";
        }

        return $output;
    }

    /**
     * Create a new resource type.
     */
    #[Sentinelable(
        name: 'Manage Resources Tool Create Type',
        description: 'Create a new global resource type.',
        category: 'Resources'
    )]
    protected function createType(Request $request): string
    {
        $name = trim($request->string('name'));
        $description = trim($request->string('description', ''));
        $config = $request->array('config');

        if (empty($name)) {
            return 'Error: A name is required to create a resource type.';
        }

        $projectId = $this->getProjectId();
        if (ResourceType::where('project_id', $projectId)->whereName($name)->exists()) {
            return "Error: A resource type with name '{$name}' already exists in this context. Use 'list_types' to see it.";
        }

        if (empty($config)) {
            return 'Error: A "config" array of field definitions is required. Each field needs a "name" and "type".';
        }

        // Basic validation of config structure
        foreach ($config as $field) {
            if (! isset($field['name']) || ! isset($field['type'])) {
                return 'Error: Each field in the "config" must have at least a "name" and a "type".';
            }
        }

        $type = ResourceType::create([
            'name' => is_array($name) ? $name : [app()->getLocale() => $name],
            'description' => $description,
            'config' => $config,
            'project_id' => $this->getProjectId(),
            'user_id' => auth()->id(),
        ]);

        return "Successfully created resource type: '{$type->name_translated}' (ID: {$type->id}). You can now store resources using this type.";
    }

    /**
     * Create a new resource of a specific type.
     */
    #[Sentinelable(
        name: 'Manage Resources Tool Create Resource',
        description: 'Create a new global resource of a specific type.',
        category: 'Resources'
    )]
    protected function createResource(Request $request): string
    {
        $typeId = $this->normalizeId($request->all()['resource_type_id'] ?? null);
        $data = $request->array('data');

        if (! $typeId || $typeId <= 0) {
            return 'Error: A valid "resource_type_id" is required.';
        }

        $type = ResourceType::find($typeId);
        if (! $type) {
            return "Error: Global resource type with ID {$typeId} not found. Use 'list_types' to find the correct ID.";
        }

        // Allow 'name' and 'description' to be extracted from 'data' if not present as top-level params
        $name = trim($request->string('name', is_array($data['name'] ?? null) ? json_encode($data['name']) : (string) ($data['name'] ?? '')));
        $description = trim($request->string('description', is_array($data['description'] ?? null) ? json_encode($data['description']) : (string) ($data['description'] ?? '')));

        if (empty($name)) {
            return 'Error: A name is required for the resource.';
        }

        if (empty($data)) {
            return 'Error: "data" must be a non-empty object mapping field names to values.';
        }

        // Remove name and description from data if they were accidentally included there
        unset($data['name'], $data['description']);

        // Basic validation and normalization of data against config
        $configNames = collect($type->fields)->pluck('name')->toArray();
        $validatedData = [];
        $ignoredFields = [];
        $missingRequiredFields = [];

        // Check for required fields and validate data
        foreach ($type->fields as $field) {
            $fieldName = $field['name'] ?? null;
            if (! $fieldName) {
                continue;
            }

            $actualKey = $this->findConfigFieldName($fieldName, array_keys($data));
            if ($actualKey) {
                $value = $data[$actualKey];
                $fieldType = $field['type'] ?? 'text';

                if (($fieldType === 'text' || $fieldType === 'textarea') && (is_array($value) || is_object($value))) {
                    $value = json_encode($value);
                }

                $validatedData[$fieldName] = $value;
            } elseif (! empty($field['required'])) {
                $missingRequiredFields[] = $fieldName;
            }
        }

        if (! empty($missingRequiredFields)) {
            return 'Error: Missing required fields: '.implode(', ', $missingRequiredFields);
        }

        // Identify ignored fields (provided in data but not in config)
        foreach ($data as $key => $value) {
            if (! $this->findConfigFieldName((string) $key, $configNames)) {
                $ignoredFields[] = (string) $key;
            }
        }

        if (empty($validatedData) && ! empty($data)) {
            return "Error: None of the fields provided in 'data' are defined in the resource type config. Defined fields: ".implode(', ', $configNames).'. Fields you provided: '.implode(', ', array_keys($data));
        }

        $resource = Resource::create([
            'resource_type_id' => $typeId,
            'name' => $name,
            'description' => $description,
            'data' => $validatedData,
            'project_id' => $type->project_id,
            'user_id' => auth()->id(),
        ]);

        $response = "Successfully created resource: '{$resource->name}' (ID: {$resource->id}) of type '{$type->name_translated}'.\n\n";
        $response .= "Current resource state:\n".$this->formatResource($resource);

        if (! empty($ignoredFields)) {
            $response .= "\n\nNote: The following fields were ignored as they are not defined in the resource type config: ".implode(', ', $ignoredFields).". Please add any extra information to the 'description' field next time.";
        }

        return $response;
    }

    /**
     * Update an existing resource.
     */
    #[Sentinelable(
        name: 'Manage Resources Tool Update Resource',
        description: 'Update an existing global resource.',
        category: 'Resources'
    )]
    protected function updateResource(Request $request): string
    {
        $resourceId = $this->normalizeId($request->all()['resource_id'] ?? null);
        if (! $resourceId || $resourceId <= 0) {
            return 'Error: A valid "resource_id" is required for updating.';
        }

        $user = auth()->user();
        $resource = Resource::visibleTo($user)
            ->with('resourceType')
            ->find($resourceId);
        if (! $resource) {
            return "Error: Global resource with ID {$resourceId} not found or unauthorized.";
        }

        $data = $request->array('data', []);
        $name = trim($request->string('name', ''));
        $description = trim($request->string('description', ''));

        if (! empty($name)) {
            $resource->name = $name;
        }

        if (! empty($description)) {
            $resource->description = $description;
        }

        if (! empty($data)) {
            $type = $resource->resourceType;
            $configNames = collect($type->fields)->pluck('name')->toArray();
            $currentData = $resource->getDecryptedData();
            $ignoredFields = [];

            foreach ($data as $key => $value) {
                $actualKey = $this->findConfigFieldName((string) $key, $configNames);
                if (! $actualKey) {
                    $ignoredFields[] = (string) $key;

                    continue;
                }

                $field = collect($type->fields)->firstWhere('name', $actualKey);
                $fieldType = $field['type'] ?? 'text';

                if (($fieldType === 'text' || $fieldType === 'textarea') && (is_array($value) || is_object($value))) {
                    $value = json_encode($value);
                }

                $currentData[$actualKey] = $value;
            }

            $resource->data = $currentData;
        }

        $resource->save();

        $response = "Successfully updated resource: '{$resource->name}' (ID: {$resource->id}).\n\n";
        $response .= "Current resource state:\n".$this->formatResource($resource);

        if (isset($ignoredFields) && ! empty($ignoredFields)) {
            $response .= "\n\nNote: The following fields were ignored: ".implode(', ', $ignoredFields).'.';
        }

        return $response;
    }

    /**
     * Get details of a specific resource.
     */
    #[Sentinelable(
        name: 'Manage Resources Tool Get Resource',
        description: 'Get details of a specific global resource.',
        category: 'Resources'
    )]
    protected function getResource(Request $request): string
    {
        $resourceId = $this->normalizeId($request->all()['resource_id'] ?? null);
        if (! $resourceId || $resourceId <= 0) {
            return 'Error: A valid "resource_id" is required.';
        }

        $user = auth()->user();
        $resource = Resource::visibleTo($user)
            ->with('resourceType')
            ->find($resourceId);
        if (! $resource) {
            return "Error: Global resource with ID {$resourceId} not found or unauthorized.";
        }

        return "Resource details:\n".$this->formatResource($resource);
    }

    private function formatResource(Resource $resource): string
    {
        $projectName = $resource->project_id ? ($resource->project?->name ?? 'Unknown Project') : 'Global';
        $output = "### Resource: {$resource->name} (ID: {$resource->id}, Project: {$projectName}, Type: {$resource->resourceType->name_translated})\n";
        $output .= "Description: {$resource->description}\n";
        $output .= "Data:\n";
        $data = $resource->data;
        $userRoles = auth()->user()?->getRoleNames()->toArray() ?? [];

        foreach ($data as $key => $value) {
            // Field-level role restriction
            $fieldConfig = collect($resource->resourceType->fields)->firstWhere('name', $key);
            if ($fieldConfig && ! empty($fieldConfig['roles']) && (! auth()->check() || ! auth()->user()->is_system)) {
                if (empty(array_intersect($userRoles, $fieldConfig['roles']))) {
                    continue; // User doesn't have the role for this field
                }
            }

            // REDACT SENSITIVE FIELDS FOR THE AI
            if ($fieldConfig && ! empty($fieldConfig['encrypted'])) {
                $value = "[ENCRYPTED_REFERENCE:{$key}]";
            } else {
                $lowerKey = strtolower($key);
                if (str_contains($lowerKey, 'password') || str_contains($lowerKey, 'ssh key') || str_contains($lowerKey, 'ssh_key') || str_contains($lowerKey, 'secret') || str_contains($lowerKey, 'token')) {
                    $value = '[REDACTED]';
                }
            }

            if (is_array($value)) {
                $value = json_encode($value);
            }

            $output .= "  - {$key}: {$value}\n";
        }

        return $output;
    }

    private function getProjectId(): ?int
    {
        return app()->bound('current_project_id') ? app('current_project_id') : null;
    }

    /**
     * Helper to find a matching config field name using fuzzy matching (underscores, spaces, case-insensitivity).
     */
    private function findConfigFieldName(string $key, array $configNames): ?string
    {
        // 1. Exact match
        if (in_array($key, $configNames)) {
            return $key;
        }

        // Normalizations
        $keyLower = strtolower($key);
        $toSpace = str_replace(['_', '-'], ' ', $keyLower);
        $toUnderscore = str_replace([' ', '-'], '_', $keyLower);

        // 2. Exact match with normalization
        if (in_array($toSpace, $configNames)) {
            return $toSpace;
        }
        if (in_array($toUnderscore, $configNames)) {
            return $toUnderscore;
        }

        // 3. Prefix/Suffix variants and case-insensitive match
        foreach ($configNames as $configName) {
            $lowerConfig = strtolower($configName);
            $normConfig = str_replace(' ', '_', $lowerConfig);

            // Case-insensitive direct match
            if ($keyLower === $lowerConfig || $keyLower === $normConfig) {
                return $configName;
            }

            // Normalization match
            if ($toSpace === $lowerConfig || $toUnderscore === $normConfig) {
                return $configName;
            }

            // Common prefix stripping (ssh_username -> username)
            $prefixes = ['ssh_', 'server_', 'site_', 'app_', 'db_', 'database_'];
            foreach ($prefixes as $prefix) {
                $stripped = str_replace($prefix, '', $keyLower);
                if ($stripped === $lowerConfig || $stripped === $normConfig) {
                    return $configName;
                }
            }

            // Common keyword mapping
            $mappings = [
                'private_key' => 'ssh_key',
                'public_key' => 'ssh_key',
                'host' => 'ip_address',
                'hostname' => 'ip_address',
                'address' => 'ip_address',
                'user' => 'username',
                'ssh_user' => 'username',
                'ssh_username' => 'username',
                'login' => 'username',
            ];

            if (isset($mappings[$keyLower]) && ($mappings[$keyLower] === $lowerConfig || $mappings[$keyLower] === $normConfig)) {
                return $configName;
            }
        }

        return null;
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['list_types', 'create_type', 'create_resource', 'update_resource', 'get_resource'])
                ->required()
                ->description('The action to perform: list_types, create_type, create_resource, update_resource, or get_resource'),

            'name' => $schema->string()
                ->description('Name for the new resource type, resource, or to update an existing resource. This is a standard field.'),
            'description' => $schema->string()
                ->description('Description for the new resource type, resource, or to update an existing resource. This is a standard field.'),

            'config' => $schema->array()
                ->description('Array of field definitions (required for create_type). Each field object should have: "name" (string), "type" (enum: text, textarea, password, number), "roles" (array of strings, optional), "required" (boolean, optional), "encrypted" (boolean, optional).')
                ->items($schema->object([
                    'name' => $schema->string()->required()->description('Name of the custom field.'),
                    'type' => $schema->string()->enum(['text', 'textarea', 'password', 'number'])->required()->description('Type of the custom field.'),
                    'roles' => $schema->array()->description('Optional. Roles allowed to see this field.')->items($schema->string()),
                    'required' => $schema->boolean()->description('Optional. Whether the field is required.'),
                    'encrypted' => $schema->boolean()->description('Optional. Whether the field should be encrypted.'),
                ])),

            'resource_type_id' => $schema->integer()
                ->description('The ID of the resource type (required for create_resource)'),
            'resource_id' => $schema->integer()
                ->description('The ID of the resource to update or retrieve (required for update_resource, get_resource)'),
            'data' => $schema->object()
                ->description('A JSON object of key-value pairs for custom fields (required for create_resource, optional for update_resource). Example: {"api_key": "xyz", "url": "https://..."}'),
        ];
    }
}
