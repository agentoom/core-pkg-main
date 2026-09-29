<?php

namespace Agentoom\Core\Services;

use Agentoom\Core\Attributes\Actionable;
use Agentoom\Core\Attributes\GroupVisibility;
use Agentoom\Core\Models\Resource;
use Agentoom\Core\Models\ResourceGroup;
use Agentoom\Core\Models\ResourceType;
use Agentoom\Core\Traits\NormalizesIds;
use Illuminate\Support\Facades\Auth;

#[GroupVisibility('agentoom-core')]
class ResourceManagementService
{
    use NormalizesIds;

    // ─── RESOURCE TYPES ──────────────────────────────────────

    /**
     * Create a resource type.
     *
     * @param  string  $name  Display name for the type (e.g. "API Server", "Database Credential").
     * @param  string  $description  Explanation of what this type stores.
     * @param  array  $config  Array of field definition objects. Each object must have:
     *                         - name (string): The field identifier (e.g. "api_key", "host")
     *                         - type (string): One of: text, textarea, password, number
     *                         - required (bool, optional): Whether the field is mandatory
     *                         - encrypted (bool, optional): Encrypt the value at rest
     *                         - roles (array, optional): Role names allowed to view this field
     *                         Example: [{"name": "api_url", "type": "text", "required": true}]
     * @param  string|null  $resourceGroupName  Optional resource group name to organize this type under.
     * @return string Success or error message.
     */
    #[Actionable(
        name: 'Create Resource Type',
        description: 'Create a resource type that stores a reusable field schema. '
            .'Resource types are visible to all users. '
            .'Provide a name, description, and a config array of field definitions. '
            .'Each field needs at least a "name" and a "type" (text, textarea, password, number). ',
        category: 'Data'
    )]
    public function createGlobalResourceType(
        string $name,
        string $description = '',
        array $config = [],
        ?string $resourceGroupName = null,
    ): string {
        if (empty($config)) {
            return 'Error: The "config" parameter must be a non-empty array of field definitions. Each field needs at least a "name" and a "type".';
        }

        foreach ($config as $field) {
            if (! isset($field['name']) || ! isset($field['type'])) {
                return 'Error: Each field in "config" must have at least a "name" and a "type". Given: '.json_encode($field);
            }
        }

        $groupId = null;
        if ($resourceGroupName) {
            $group = ResourceGroup::where('name', $resourceGroupName)->first();
            if ($group) {
                $groupId = $group->id;
            }
        }

        $type = ResourceType::create([
            'name' => [app()->getLocale() => $name],
            'description' => $description,
            'config' => $config,
            'resource_group_id' => $groupId,
            'user_id' => Auth::id(),
        ]);

        return "Successfully created resource type: '{$type->name_translated}' (ID: {$type->id}). "
            .'You can now create resources of this type using "Create Resource".';
    }

    /**
     * Update a resource type.
     *
     * @param  mixed  $typeId  The ID of the resource type to update.
     * @param  string|null  $name  New display name.
     * @param  string|null  $description  New description.
     * @param  array|null  $config  New field definitions (replaces existing config entirely).
     *                              Example: [{"name": "api_key", "type": "password", "encrypted": true}]
     * @return string Success or error message.
     */
    #[Actionable(
        name: 'Update Resource Type',
        description: 'Update an existing resource type. Only the fields you provide will be changed. '
            .'Omitting a field keeps its current value. ',
        category: 'Data'
    )]
    public function updateGlobalResourceType(
        mixed $typeId,
        ?string $name = null,
        ?string $description = null,
        ?array $config = null,
    ): string {
        $typeId = $this->normalizeId($typeId);
        $type = ResourceType::find($typeId);

        if (! $type) {
            return "Error: Resource type with ID {$typeId} not found.";
        }

        if ($name !== null) {
            $type->name = [app()->getLocale() => $name];
        }
        if ($description !== null) {
            $type->description = $description;
        }
        if ($config !== null) {
            $type->config = $config;
        }

        $type->save();

        return "Successfully updated resource type: '{$type->name_translated}' (ID: {$type->id}).";
    }

    // ─── RESOURCES ───────────────────────────────────────────

    /**
     * Create a resource inside an existing resource type.
     *
     * @param  string  $name  A human-readable name (e.g. "Production API", "Staging DB").
     * @param  string  $description  A description of what this resource contains.
     * @param  array  $data  A JSON object mapping field names to values. Must match the resource type's field definitions.
     *                       Example: {"host": "example.com", "port": "443", "api_key": "abc123"}
     * @param  mixed  $resourceTypeId  The ID of the resource type (required). Get this from the resource type list.
     * @return string Success or error message.
     */
    #[Actionable(
        name: 'Create Resource',
        description: 'Create a new resource inside a resource type. '
            .'Requires a name, resource_type_id, and a data object matching the type\'s field definitions. '
            .'Resources are visible across the entire organization.',
        category: 'Data'
    )]
    public function createGlobalResource(
        string $name,
        string $description = '',
        array $data = [],
        mixed $resourceTypeId = null,
    ): string {
        $resourceTypeId = $this->normalizeId($resourceTypeId);

        if (! $resourceTypeId) {
            return 'Error: A valid "resource_type_id" is required. First create a resource type using "Create Resource Type", then use its ID here.';
        }

        $type = ResourceType::find($resourceTypeId);

        if (! $type) {
            return "Error: Resource type with ID {$resourceTypeId} not found. "
                ."Use 'Create Resource Type' first or check the resource_type_id.";
        }

        $validatedData = $this->validateDataAgainstConfig($type, $data, $missingRequired);
        if (! empty($missingRequired)) {
            return 'Error: Missing required fields: '.implode(', ', $missingRequired)
                .'. These fields are required by the resource type config and must be provided in the "data" parameter.';
        }

        $resource = Resource::create([
            'resource_type_id' => $type->id,
            'name' => $name,
            'description' => $description,
            'data' => $validatedData,
            'user_id' => Auth::id(),
        ]);

        return "Successfully created resource: '{$resource->name}' (ID: {$resource->id}) of type '{$type->name_translated}'.";
    }

    /**
     * Update a resource.
     *
     * @param  mixed  $resourceId  The ID of the resource to update.
     * @param  string|null  $name  New name for the resource.
     * @param  string|null  $description  New description.
     * @param  array|null  $data  A JSON object with field values to update. Only included fields are changed.
     *                            Example: {"host": "new-host.com", "port": "8080"}
     * @param  array|null  $appendFields  List of field names whose values should be APPENDED/CONCATENATED
     *                                    instead of replaced. For string fields, the new value is added
     *                                    after a blank line. For array fields, values are merged.
     *                                    Example: ["activity_log", "notes"]
     * @return string Success or error message.
     */
    #[Actionable(
        name: 'Update Resource',
        description: 'Update an existing resource. Provide the resource_id and any fields to change. '
            .'Use "append_fields" to list field names that should accumulate values instead of replacing them '
            .'(useful for logs, notes, changelogs).',
        category: 'Data'
    )]
    public function updateGlobalResource(
        mixed $resourceId,
        ?string $name = null,
        ?string $description = null,
        ?array $data = null,
        ?array $appendFields = null,
    ): string {
        $resourceId = $this->normalizeId($resourceId);

        $resource = Resource::visibleTo(Auth::user())->with('resourceType')->find($resourceId);
        if (! $resource) {
            return "Error: Resource with ID {$resourceId} not found or unauthorized.";
        }

        return $this->applyUpdates($resource, $resource->resourceType, $name, $description, $data, $appendFields);
    }

    // ─── PRIVATE HELPERS ─────────────────────────────────────

    private function applyUpdates(
        Resource $resource,
        ResourceType $type,
        ?string $name,
        ?string $description,
        ?array $data,
        ?array $appendFields,
    ): string {
        if ($name !== null) {
            $resource->name = $name;
        }

        if ($description !== null) {
            $resource->description = $description;
        }

        if ($data !== null) {
            $currentData = $resource->getDecryptedData();

            foreach ($data as $key => $value) {
                $fieldConfig = collect($type->fields)->firstWhere('name', $key);
                if (! $fieldConfig) {
                    continue;
                }

                $fieldType = $fieldConfig['type'] ?? 'text';
                if (($fieldType === 'text' || $fieldType === 'textarea') && (is_array($value) || is_object($value))) {
                    $value = json_encode($value);
                }

                $shouldAppend = $appendFields !== null && in_array($key, $appendFields, true);

                if ($shouldAppend && array_key_exists($key, $currentData)) {
                    $existing = $currentData[$key];
                    $decodedValue = $value;

                    if (is_string($value)) {
                        $jsonValue = json_decode($value, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $decodedValue = $jsonValue;
                        }
                    }

                    if (is_array($existing) && is_array($decodedValue)) {
                        $currentData[$key] = array_merge($existing, $decodedValue);
                    } elseif (is_string($existing)) {
                        $currentData[$key] = $existing."\n\n".(string) $value;
                    } else {
                        $currentData[$key] = $value;
                    }
                } else {
                    $currentData[$key] = $value;
                }
            }

            $resource->data = $currentData;
        }

        $resource->save();

        return "Successfully updated resource: '{$resource->name}' (ID: {$resource->id}).";
    }

    private function validateDataAgainstConfig(
        ResourceType $type,
        array $data,
        ?array &$missingRequired = null,
    ): array {
        $validated = [];
        $missingRequired = [];

        foreach ($type->fields as $field) {
            $fieldName = $field['name'] ?? null;
            if (! $fieldName) {
                continue;
            }

            if (array_key_exists($fieldName, $data)) {
                $value = $data[$fieldName];
                $fieldType = $field['type'] ?? 'text';

                if (($fieldType === 'text' || $fieldType === 'textarea') && (is_array($value) || is_object($value))) {
                    $value = json_encode($value);
                }

                $validated[$fieldName] = $value;
            } elseif (! empty($field['required'])) {
                $missingRequired[] = $fieldName;
            }
        }

        return $validated;
    }
}
