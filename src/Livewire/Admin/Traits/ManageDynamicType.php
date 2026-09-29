<?php

namespace Agentoom\Core\Livewire\Admin\Traits;

use Agentoom\Core\Models\Language;
use Agentoom\Core\Models\Project;
use Agentoom\Core\Models\ResourceGroup;

trait ManageDynamicType
{
    public array $names = [];

    public string $description = '';

    public array $config = [];

    public ?int $resourceGroupId = null;

    public ?int $projectId = null;

    public ?int $user_id = null;

    public bool $is_editing = false;

    public function addField(): void
    {
        $this->config[] = [
            'name' => '',
            'type' => 'text',
            'required' => false,
            'encrypted' => false,
            'options' => [],
        ];
    }

    public function addOption(int $fieldIndex): void
    {
        if (! isset($this->config[$fieldIndex]['options'])) {
            $this->config[$fieldIndex]['options'] = [];
        }
        $this->config[$fieldIndex]['options'][] = '';
    }

    public function removeOption(int $fieldIndex, int $optionIndex): void
    {
        unset($this->config[$fieldIndex]['options'][$optionIndex]);
        $this->config[$fieldIndex]['options'] = array_values($this->config[$fieldIndex]['options']);
    }

    public function removeField(int $index): void
    {
        unset($this->config[$index]);
        $this->config = array_values($this->config);
    }

    public function moveFieldUp(int $index): void
    {
        if ($index <= 0) {
            return;
        }

        $temp = $this->config[$index - 1];
        $this->config[$index - 1] = $this->config[$index];
        $this->config[$index] = $temp;
    }

    public function moveFieldDown(int $index): void
    {
        if ($index >= count($this->config) - 1) {
            return;
        }

        $temp = $this->config[$index + 1];
        $this->config[$index + 1] = $this->config[$index];
        $this->config[$index] = $temp;
    }

    protected function validateDynamicType()
    {
        // Filter empty options
        foreach ($this->config as $index => $field) {
            if (isset($field['options'])) {
                $this->config[$index]['options'] = array_values(array_filter($field['options'], function ($option) {
                    return ! empty(trim((string) $option));
                }));
            }
        }

        return $this->validate([
            'names.*' => 'required|string|max:255',
            'description' => 'nullable|string',
            'resourceGroupId' => 'required|exists:resource_groups,id',
            'projectId' => 'nullable|exists:projects,id',
            'user_id' => 'nullable|exists:users,id',
            'config' => 'required|array|min:1',
            'config.*.name' => 'required|string|max:255',
            'config.*.type' => 'required|string|in:text,number,textarea,date,password,select,radio,checkbox',
            'config.*.required' => 'boolean',
            'config.*.encrypted' => 'boolean',
            'config.*.options' => 'nullable|array',
        ]);
    }

    protected function getDynamicTypeData()
    {
        return [
            'name' => $this->names,
            'description' => $this->description,
            'config' => $this->config,
            'resource_group_id' => $this->resourceGroupId,
            'project_id' => $this->projectId,
            'user_id' => $this->user_id ?? auth()->id(),
        ];
    }

    protected function getDynamicTypeRenderData()
    {
        return [
            'projects' => Project::orderBy('name')->get(),
            'users' => auth()->user()->getAssignableUsers(),
            'languages' => Language::where('is_active', true)->get(),
            'resourceGroups' => ResourceGroup::visibleTo(auth()->user())->get(),
        ];
    }
}
